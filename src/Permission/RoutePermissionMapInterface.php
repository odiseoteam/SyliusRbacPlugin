<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

interface RoutePermissionMapInterface
{
    /** @return string|null the permission the route requires, or null when none is known */
    public function permissionFor(string $routeName): ?string;

    /**
     * Same question for a path, so callers holding a URL rather than a route name — a menu item
     * built with `setUri()` — do not have to guess which route it belongs to.
     */
    public function permissionForPath(string $path): ?string;

    public function isExcluded(string $routeName): bool;
}
