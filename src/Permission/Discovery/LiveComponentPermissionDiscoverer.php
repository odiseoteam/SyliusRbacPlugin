<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

/**
 * Reports the live components nothing has a permission mapped for.
 *
 * Emits no definitions of its own: every permission a live component resolves to already exists:
 * `sylius.taxon.update` because the update route was found, `sylius.dashboard.view` because the
 * dashboard route was declared. This discoverer only has to say which components are left over.
 */
final readonly class LiveComponentPermissionDiscoverer implements PermissionDiscovererInterface
{
    /**
     * @param list<string> $liveComponents every admin live component name, from the
     *        `sylius.live_component.admin` tag
     * @param list<string> $mappedComponents component names a permission is already resolved for
     */
    public function __construct(
        private array $liveComponents = [],
        private array $mappedComponents = [],
    ) {
    }

    public function discover(): DiscoveredPermissions
    {
        $unmapped = [];

        foreach ($this->liveComponents as $component) {
            if (!in_array($component, $this->mappedComponents, true)) {
                $unmapped[$component] = 'is a live component with no permission mapped for it';
            }
        }

        return new DiscoveredPermissions([], $unmapped);
    }
}
