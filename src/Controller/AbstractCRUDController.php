<?php

namespace Kikwik\AdminkBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Kikwik\AdminkBundle\Interfaces\CRUDControllerInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
        private RequestStack $requestStack,
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

    protected function getFilterFormClass(): ?string
    {
        return null;
    }

    protected function getSortPaths(): array
    {
        return [];
    }

    protected abstract function getFormClass(): ?string;

    protected function getExportFields(): ?array
    {
        return null;
    }

    /**************************************/
    /* SESSION                            */
    /**************************************/

    private function getSessionAttributes()
    {
        return $this->requestStack->getSession()->get($this->baseRouteName,[]);
    }
    private function setSessionAttributes(array $attributes)
    {
        $this->requestStack->getSession()->set($this->baseRouteName,$attributes);
    }


    private function getCurrentSort(): ?array
    {
        $sessioneAttributes = $this->getSessionAttributes();
        return $sessioneAttributes['sort'] ?? null;
    }

    private function setCurrentSort(string $field, string $dir = 'asc')
    {
        $sessioneAttributes = $this->getSessionAttributes();
        $sessioneAttributes['sort'] = [$field,$dir];
        $this->setSessionAttributes($sessioneAttributes);
    }

    private function getCurrentFilters(): array
    {
        $sessioneAttributes = $this->getSessionAttributes();
        return $sessioneAttributes['filter'] ?? [];
    }

    private function setCurrentFilters(array $filter)
    {
        $sessioneAttributes = $this->getSessionAttributes();
        $sessioneAttributes['filter'] = $filter;
        $this->setSessionAttributes($sessioneAttributes);
    }

    /**************************************/
    /* ROUTES                             */
    /**************************************/

    #[Route('/', name: '_list')]
    public function list(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] bool $filter_reset = false,
        #[MapQueryParameter] string $sortField = '',
        #[MapQueryParameter] string $sortDir = 'asc',
        $_locale = 'it'
    ): Response
    {
        if($sortField)
        {
            $this->setCurrentSort($sortField, $sortDir);
        }
        if($filter_reset)
        {
            $this->setCurrentFilters([]);
        }

        // filter form
        $filterForm = null;
        $filterFormType =$this->getFilterFormClass();
        if($filterFormType)
        {
            $filterForm = $this->formFactory->create($filterFormType, $this->getCurrentFilters(), [
                'action'=>$this->urlGenerator->generate($this->baseRouteName.'_list'),
                'method' => 'get',
                'validation_groups' => false,
                'csrf_protection' => false,
            ]);
            $filterForm->handleRequest($request);
            if($filterForm->isSubmitted())
            {
                $this->setCurrentFilters($filterForm->getData());
            }
        }

        $pager = Pagerfanta::createForCurrentPageWithMaxPerPage(
            new QueryAdapter($this->getListQuery()),
            $page,
            20
        );

        $content = $this->twig->render('@KikwikAdmink/crud/list.html.twig', [
            'pluralName' => $this->getPluralName(),
            'listFields' => $this->getListFields(),
            'sortPaths' => $this->getSortPaths(),
            'currentSort' => $this->getCurrentSort(),
            'exportFields' => $this->getExportFields(),
            'baseRouteName' => $this->baseRouteName,
            'pager'=>$pager,
            'filterForm'=>$filterForm?->createView(),
        ]);
        return new Response($content);
    }

    #[Route('/export', name: '_export')]
    public function export()
    {
        ini_set('memory_limit','500M');
        $qb = $this->entityManager->getRepository($this->getEntityClass())->createQueryBuilder('object');
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

    private function getListQuery(): QueryBuilder
    {
        $repository = $this->entityManager->getRepository($this->getEntityClass());
        if(method_exists($repository, 'createAdminQueryBuilder'))
        {
            $qb = $repository->createAdminQueryBuilder($this->getCurrentFilters());
        }
        elseif(in_array(TranslatableInterface::class, class_implements($this->getEntityClass())))
        {

            $qb = $repository->createQueryBuilder('object')
                ->leftJoin('object.translations','translation')
                ->addSelect('translation')
            ;
            if($this->getCurrentSort())
            {
                list($sortField, $sortDir) = $this->getCurrentSort();
                $sortPaths = $this->getSortPaths();
                dump($sortPaths[$sortField]);
                if(str_starts_with($sortPaths[$sortField],'translation')){
                    $qb->andWhere('translation.locale = :locale')->setParameter('locale',$this->requestStack->getCurrentRequest()->getLocale());
                }
            }
        }
        else
        {
            $qb = $repository->createQueryBuilder('object');
        }
        if($this->getCurrentSort())
        {
            list($sortField, $sortDir) = $this->getCurrentSort();
            $sortPaths = $this->getSortPaths();
            if(isset($sortPaths[$sortField]))
            {
                $qb->orderBy($sortPaths[$sortField], $sortDir);
            }
        }
        return $qb;
    }
}