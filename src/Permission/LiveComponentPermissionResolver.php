<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

/**
 * Answers "what permission does invoking this live component action need?".
 *
 * `sylius_admin_live_component` is one route shared by every live component in the admin, so no
 * single permission means the right thing for all of it. Each component is mapped to the
 * permission its own screen already checks.
 *
 * `sylius_admin:taxon:tree` is the one exception: its default action only renders the tree, but
 * `moveUp`/`moveDown` reorder it for real (`TreeComponent::moveUp()` calls `$manager->flush()`
 * directly, with no permission check of its own). Splitting it in two is what lets a read-only
 * role browse the tree without also being able to reorder it.
 */
final readonly class LiveComponentPermissionResolver implements LiveComponentPermissionResolverInterface
{
    private const DEFAULT_ACTION = 'get';

    /** @param array<string, string> $componentPermissions component name => permission identifier */
    public function __construct(
        private array $componentPermissions = [],
    ) {
    }

    public function resolve(string $component, string $action): ?string
    {
        if (self::TAXON_TREE_COMPONENT === $component) {
            return self::DEFAULT_ACTION === $action ? 'sylius.taxon.index' : 'sylius.taxon.update';
        }

        return $this->componentPermissions[$component] ?? null;
    }
}
