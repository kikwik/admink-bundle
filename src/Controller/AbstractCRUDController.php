<?php

namespace Kikwik\AdminkBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Kikwik\AdminkBundle\Interfaces\CRUDControllerInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

abstract class AbstractCRUDController implements CRUDControllerInterface
{
    protected string $baseRouteName;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        private Environment $twig,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
    )
    {
        // find baseRoutename from Route attribute
        $reflection = new \ReflectionClass($this);
        foreach($reflection->getAttributes() as $attribute)
        {
            if($attribute->getName() == Route::class)
            {
                $route = $attribute->newInstance();
                $this->baseRouteName = $route->getName();
            }
        }
    }

    /**************************************/
    /* CONFIGURATION                      */
    /**************************************/

    protected function getSingularName(): string
    {
        $reflection_class = new \ReflectionClass($this->getEntityClass());
        return $reflection_class->getShortName();
    }

    protected function getPluralName(): string
    {
        $singular = $this->getSingularName();
        $lastChar = substr($singular, -1);
        switch($lastChar)
        {
            case 'a': return substr($singular, 0, -1).'e';
            case 'e': return substr($singular, 0, -1).'i';
            case 'o': return substr($singular, 0, -1).'i';
        }
        return $singular;
    }

    protected abstract function getEntityClass(): string;

    protected function getRepository(): EntityRepository
    {
        return $this->entityManager->getRepository($this->getEntityClass());
    }

    protected abstract function getListFields(): array;

    protected function getFormClass(): ?string
    {
        return null;
    }

    protected function getExportFields(): ?array
    {
        return null;
    }

    /**************************************/
    /* ROUTES                             */
    /**************************************/

    #[Route('/', name: '_list')]
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] array $filter = [],
        $_locale = 'it'
    ): Response
    {
        $pager = Pagerfanta::createForCurrentPageWithMaxPerPage(
            new QueryAdapter($this->getListQuery($filter)),
            $page,
            20
        );

        $content = $this->twig->render('@KikwikAdmink/crud/list.html.twig', [
            'pluralName' => $this->getPluralName(),
            'listFields' => $this->getListFields(),
            'exportFields' => $this->getExportFields(),
            'baseRouteName' => $this->baseRouteName,
            'pager'=>$pager,
            'filter'=>$filter,
        ]);
        return new Response($content);
    }

    #[Route('/export', name: '_export')]
    public function export()
    {
        ini_set('memory_limit','500M');
        $qb = $this->getListQuery([]);
        $response = new StreamedResponse(function () use ($qb) {
            $csv = fopen('php://output', 'w+');
            fputcsv($csv, array_values($this->getExportFields()), ';');
            $data = $qb->getQuery()->iterate();
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            while (false !== ($line = $data->next()))
            {
                $object = $line[0];
                $csvData = [];
                foreach($this->getExportFields() as $property => $label)
                {
                    try{
                        $value = '';

                        if(str_starts_with($property, 'translations.'))
                        {
                            list($transRelation, $transLocale, $transProperty) = explode('.',$property);
                            $trans = $propertyAccessor->getValue($object, $transRelation);
                            /** @var TranslationInterface $tran */
                            foreach($trans as $tran)
                            {
                                if($tran->getLocale() == $transLocale)
                                {
                                    $value = $propertyAccessor->getValue($tran, $transProperty);
                                    break;
                                }
                            }
                        }
                        else
                        {
                            $value = $propertyAccessor->getValue($object, $property);
                            if(is_array($value))
                            {
                                $value = implode(', ',$value);
                            }
                            if($value instanceof \DateTimeInterface)
                            {
                                $value = $value->format('Y-m-d H:i:s');
                            }
                            if(is_bool($value))
                            {
                                $value = $value ? 'SI' : 'NO';
                            }
                        }

                        $csvData[] = strip_tags($value);
                    }
                    catch (\Throwable $e)
                    {
                        $csvData[] = $e->getMessage();
                    }
                }
                fputcsv($csv, $csvData, ';');
            }
            fclose($csv);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$this->getPluralName().'.csv"');
        return $response;
    }

    #[Route('/new', name: '_new')]
    public function new(Request $request, EntityManagerInterface $entityManager)
    {
        /** @var Form $form */
        $form = $this->formFactory->create($this->getFormClass());
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager->persist($form->getData());
            $entityManager->flush();

            return new RedirectResponse($this->urlGenerator->generate($this->baseRouteName.'_list'), 302);
        }

        $content = $this->twig->render('@KikwikAdmink/crud/new.html.twig',[
            'singularName' => $this->getSingularName(),
            'form'=>$form->createView(),
            'baseRouteName' => $this->baseRouteName,
        ]);
        return new Response($content);
    }

    #[Route('/{id}/edit', name: '_edit')]
    public function edit(int $id, Request $request)
    {
        $object = $this->getRepository()->find($id);

        /** @var Form $form */
        $form = $this->formFactory->create($this->getFormClass(), $object);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $this->entityManager->persist($form->getData());
            $this->entityManager->flush();

            return new RedirectResponse($this->urlGenerator->generate($this->baseRouteName.'_list'), 302);
        }

        $content = $this->twig->render('@KikwikAdmink/crud/edit.html.twig',[
            'singularName' => $this->getSingularName(),
            'object'=>$object,
            'form'=>$form->createView(),
            'baseRouteName' => $this->baseRouteName,
        ]);
        return new Response($content);
    }

    /**************************************/
    /* HELPERS                            */
    /**************************************/

    protected function getListQuery(array $filters): QueryBuilder
    {
        return $this->entityManager->getRepository($this->getEntityClass())
            ->createQueryBuilder('object');
    }
}