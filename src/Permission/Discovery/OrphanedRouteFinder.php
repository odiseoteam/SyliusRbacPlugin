<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Symfony\Component\Routing\RouterInterface;

/**
 * Which entries in a route-keyed declaration point at a route that no longer exists.
 *
 * `route_permissions` and `excluded_routes` are hand-written strings pointing into somebody
 * else's routing table, so they rot exactly when Sylius renames a route -- silently, since a
 * rename also shows up as a new, unrelated route nothing covers, which is loud enough on its own
 * to hide the stale entry sitting next to it. Shared between the debug command and
 * `PermissionDeclarationsTest` so the two cannot drift apart on what counts as orphaned.
 */
final readonly class OrphanedRouteFinder
{
    public function __construct(private RouterInterface $router)
    {
    }

    /**
     * @param array<array-key, string> $declared route names, as a list or as the keys of a map
     *
     * @return list<string>
     */
    public function find(array $declared): array
    {
        $routes = $this->router->getRouteCollection();
        $names = array_is_list($declared) ? $declared : array_keys($declared);

        return array_values(array_filter(
            $names,
            static fn (string $name): bool => null === $routes->get($name),
        ));
    }
}
