<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Checker;

interface RouteNameCheckerInterface
{
    public function isAdminRoute(string $routeName): bool;
}
