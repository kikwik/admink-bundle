<?php

namespace Kikwik\AdminkBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
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

        // TODO: load routes....

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'kikwik_admink_routes' === $type;
    }

}