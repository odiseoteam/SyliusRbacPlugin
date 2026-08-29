<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Scope;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;

/**
 * The free plugin does not scope: holding a permission means holding it everywhere.
 *
 * Deliberately permissive rather than absent. If no resolver were wired the voter would have to
 * guard against a null collaborator, and "no scoping configured" would look the same as
 * "scoping said no" at the call site.
 */
final class UnrestrictedScopeResolver implements ScopeResolverInterface
{
    public function isInScope(
        AdministrationRoleAwareInterface $administrator,
        PermissionIdentifier $permission,
        mixed $subject,
    ): bool {
        return true;
    }
}
