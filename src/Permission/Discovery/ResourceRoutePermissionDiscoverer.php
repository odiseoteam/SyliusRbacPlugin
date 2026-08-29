<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Derives the permission vocabulary from the routes that actually enforce it.
 *
 * Routes are the source rather than the resource registry, because the identifier checked at
 * runtime is built from the route: `RequestConfiguration::getPermission()` combines the
 * resource metadata with the action the controller passes to `isGrantedOr403()`. Enumerating
 * resources instead would invent identifiers nobody checks and miss the ones that are checked.
 */
final readonly class ResourceRoutePermissionDiscoverer implements PermissionDiscovererInterface
{
    /**
     * @param list<string> $handledRoutes routes already covered by a declaration or marked
     *        public, passed over in silence so the report only names routes needing attention
     * @param string $adminPathName path segment the admin lives under. Admin routes are matched
     *        by path prefix, the same way the firewall does it.
     */
    public function __construct(
        private RouterInterface $router,
        private RoutePermissionResolver $resolver,
        private array $handledRoutes = [],
        private string $adminPathName = 'admin',
    ) {
    }

    public function discover(): DiscoveredPermissions
    {
        $definitions = [];
        $unprotectedRoutes = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            $name = (string) $name;

            if (!$this->isAdminRoute($route) || in_array($name, $this->handledRoutes, true)) {
                continue;
            }

            /**
             * A route that never asked for a permission is reported too, not just skipped. This
             * is the case that matters to whoever installs a plugin with its own controllers or
             * adds an admin route of their own: without it the route is simply invisible, and
             * the tool says nothing precisely when it should be saying something.
             */
            if (!$this->resolver->enforcesPermission($route)) {
                $unprotectedRoutes[$name] = 'declares no permission, and nothing declares one for it';

                continue;
            }

            try {
                $definitions[] = new PermissionDefinition($this->resolver->resolve($route));
            } catch (UnmappableRouteException $exception) {
                $unprotectedRoutes[$name] = $exception->getMessage();
            }
        }

        return new DiscoveredPermissions($definitions, $unprotectedRoutes);
    }

    private function isAdminRoute(Route $route): bool
    {
        return str_starts_with($route->getPath(), '/' . $this->adminPathName);
    }
}
