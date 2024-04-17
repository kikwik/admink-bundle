<?php

namespace Kikwik\AdminkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class DashboardController
{
    public function __construct(
        private Environment $twig,
        private KernelInterface $kernel,
    )
    {
    }

    public function dashboard(): Response
    {
        return new Response($this->twig->render('@KikwikAdmink/dashboard/dashboard.html.twig',[
        ]));
    }

    public function clearCache()
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'cache:clear',
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);
        $commandOutput = $output->fetch();

        $alertClass = strpos($commandOutput,'[OK]')!==false ? 'alert-success' : 'alert-danger';

        return new Response($this->twig->render('@KikwikAdmink/dashboard/clearCache.html.twig',[
            'alertClass' => $alertClass,
            'content' => $commandOutput,
        ]));
    }

}