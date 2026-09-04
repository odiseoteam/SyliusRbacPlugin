<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Scope;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;

/**
 * Second half of the decision: having the permission, does it reach *this* object?
 *
 * `PermissionVoter` calls this on every decision it would otherwise grant, so an implementation
 * returning false denies. Replace or decorate the service to restrict a permission to a subset
 * of the data — orders of one channel, for instance. The shipped implementation allows
 * everything.
 *
 * `$subject` is whatever the caller passed to `isGranted()`: an entity, or null when the check
 * is about a screen rather than a record.
 */
interface ScopeResolverInterface
{
    public function isInScope(
        AdministrationRoleAwareInterface $administrator,
        PermissionIdentifier $permission,
        mixed $subject,
    ): bool;
}
