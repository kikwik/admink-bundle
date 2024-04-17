<?php

namespace Kikwik\AdminkBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteLoader extends Loader
{
    private bool $isLoaded = false;

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if (true === $this->isLoaded) {
            throw new \RuntimeException('Do not add the "Kikwik\AdminkBundle\Routing\RouteLoader" loader twice');
        }
        $routes = new RouteCollection();

        // dashboard
        $routes->add('kikwik_admink_dashboard', new Route(
            '/',
            [
                '_controller' => 'kikwik_admink.controller.dashboard_controller::dashboard',
            ],
            [],
            [],
            null,
            [],
            ['GET'],
            null
        ));
        $routes->add('kikwik_admink_clear_cache', new Route(
            '/_clear_cache',
            [
                '_controller' => 'kikwik_admink.controller.dashboard_controller::clearCache',
            ],
            [],
            [],
            null,
            [],
            ['GET'],
            null
        ));



        $this->isLoaded = true;
        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'kikwik_admink_routes' === $type;
    }

}