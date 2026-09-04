<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

interface LiveComponentPermissionResolverInterface
{
    /** The one route every live component shares, regardless of which one is named. */
    public const ROUTE = 'sylius_admin_live_component';

    /** Resolved by its own rule rather than a declared entry -- see the implementation. */
    public const TAXON_TREE_COMPONENT = 'sylius_admin:taxon:tree';

    /** @return string|null the permission required, or null when the component is not mapped */
    public function resolve(string $component, string $action): ?string;
}
