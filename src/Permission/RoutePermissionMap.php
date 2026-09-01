<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\UnmappableRouteException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Answers "what permission does this route name require?".
 *
 * The same question the HTTP listener answers per request, asked ahead of time by the surfaces
 * that decide what to *show*: the menu and the grid action buttons. Both have to agree with
 * what the request will do, so both read it from here rather than from a table of their own.
 */
final class RoutePermissionMap implements RoutePermissionMapInterface
{
    /** @var array<string, string>|null */
    private ?array $map = null;

    /**
     * @param array<string, string> $declaredPermissions route name => permission identifier
     * @param list<string> $excludedRoutes
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RoutePermissionResolver $resolver,
        private readonly array $declaredPermissions = [],
        private readonly array $excludedRoutes = [],
    ) {
    }

    public function permissionFor(string $routeName): ?string
    {
        return $this->map()[$routeName] ?? null;
    }

    public function permissionForPath(string $path): ?string
    {
        if (!str_starts_with($path, '/')) {
            return null;
        }

        try {
            $parameters = $this->router->match(parse_url($path, \PHP_URL_PATH) ?: $path);
        } catch (\Throwable) {
            return null;
        }

        $route = $parameters['_route'] ?? null;

        return is_string($route) ? $this->permissionFor($route) : null;
    }

    public function isExcluded(string $routeName): bool
    {
        return in_array($routeName, $this->excludedRoutes, true);
    }

    /** @return array<string, string> */
    private function map(): array
    {
        if (null !== $this->map) {
            return $this->map;
        }

        $map = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            if (!$this->resolver->enforcesPermission($route)) {
                continue;
            }

            try {
                $map[(string) $name] = $this->resolver->resolve($route)->toString();
            } catch (UnmappableRouteException) {
                continue;
            }
        }

        // Declarations win: they exist precisely for the routes discovery cannot name.
        return $this->map = [...$map, ...$this->declaredPermissions];
    }
}
