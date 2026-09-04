<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Symfony\Component\HttpFoundation\Request;

interface EntityAutocompletePermissionResolverInterface
{
    /** The one route every autocomplete field shares, regardless of alias. */
    public const ROUTE = 'sylius_admin_entity_autocomplete';

    /** @return string|null the permission required, or null when no entity could be resolved */
    public function resolve(string $alias, Request $request): ?string;
}
