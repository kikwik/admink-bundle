<?php

namespace Kikwik\AdminkBundle\Service;

use Symfony\Component\Routing\RouterInterface;

class AdminKonfig
{
    public function __construct(
        private RouterInterface $router,
        private string $adminTitle,
        private array $assets,
        private array $routes,
        private array $sidebar,
    )
    {
    }


    public function getAdminTitle(): string
    {
        return $this->adminTitle;
    }

    public function getAdminLogo(): string
    {
        return $this->assets['logo'];
    }

    public function getAdminFavicon(): string
    {
        return $this->assets['favicon'];
    }

    public function getLoginRoute(): ?string
    {
        $route = $this->routes['login'];
        return $this->router->getRouteCollection()->get($route)
            ? $route
            : null;
    }

    public function getLogoutRoute(): ?string
    {
        $route = $this->routes['logout'];
        return $this->router->getRouteCollection()->get($route)
            ? $route
            : null;
    }

    public function getChangePasswordRoute(): ?string
    {
        $route = $this->routes['change_password'];
        return $this->router->getRouteCollection()->get($route)
            ? $route
            : null;
    }

    public function getSidebar(): array
    {
        return $this->sidebar;
    }
}