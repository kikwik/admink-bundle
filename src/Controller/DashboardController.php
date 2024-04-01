<?php

namespace Kikwik\AdminkBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private Environment $twig,
    )
    {
    }

    public function index(): Response
    {
        $content = $this->twig->render('@KikwikAdmink/dashboard.html.twig',[
        ]);

        return new Response($content);
    }

}