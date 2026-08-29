<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

/**
 * Runs every discoverer and pools what they found.
 *
 * Order matters only for readability: duplicate identifiers are merged by the registry, and
 * merging is what lets a declaration enrich something discovery already found without either
 * side knowing about the other.
 */
final readonly class ChainPermissionDiscoverer implements PermissionDiscovererInterface
{
    /** @param iterable<PermissionDiscovererInterface> $discoverers */
    public function __construct(private iterable $discoverers = [])
    {
    }

    public function discover(): DiscoveredPermissions
    {
        $definitions = [];
        $unprotectedRoutes = [];

        foreach ($this->discoverers as $discoverer) {
            $found = $discoverer->discover();

            $definitions = [...$definitions, ...$found->definitions];
            $unprotectedRoutes = [...$unprotectedRoutes, ...$found->unprotectedRoutes];
        }

        return new DiscoveredPermissions($definitions, $unprotectedRoutes);
    }
}
