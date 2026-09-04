<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;

/**
 * Unions an administrator's roles into one set of patterns, once per request.
 *
 * A single admin page asks the voter dozens of times, so the union is computed once per
 * administrator.
 *
 * The cache is a `WeakMap` keyed by the administrator object, not a plain array: under a
 * long-running worker the service outlives the request, and a plain cache would serve the first
 * user of the worker to every later one. A `WeakMap` entry disappears with its object.
 *
 * Consequence: changing an administrator's roles takes effect on the next request.
 */
final class EffectivePermissionsProvider implements EffectivePermissionsProviderInterface
{
    /** @var \WeakMap<AdministrationRoleAwareInterface, EffectivePermissions> */
    private \WeakMap $cache;

    public function __construct()
    {
        $this->cache = new \WeakMap();
    }

    public function forAdministrator(AdministrationRoleAwareInterface $administrator): EffectivePermissions
    {
        return $this->cache[$administrator] ??= $this->compute($administrator);
    }

    private function compute(AdministrationRoleAwareInterface $administrator): EffectivePermissions
    {
        $patterns = [];

        /** @var AdministrationRoleInterface $role */
        foreach ($administrator->getAdministrationRoles() as $role) {
            foreach ($role->getPermissionPatterns() as $pattern) {
                $patterns[$pattern->toString()] = $pattern;
            }
        }

        return EffectivePermissions::of(array_values($patterns));
    }
}
