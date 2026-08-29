<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Voter;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Security\EffectivePermissionsProviderInterface;
use Odiseo\SyliusRbacPlugin\Security\Scope\ScopeResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The single place where "may this administrator do this?" is answered.
 *
 * A Symfony voter rather than a listener of its own, so the answer is the same whether the
 * question comes from a controller, a Twig `is_granted()`, an API Platform `security:`
 * expression or a grid action.
 *
 * Two conditions have to hold: the administrator must hold a pattern matching the permission,
 * and the scope resolver must accept the subject — see `ScopeResolverInterface`.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    public function __construct(
        private readonly EffectivePermissionsProviderInterface $permissions,
        private readonly ScopeResolverInterface $scopeResolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== $this->parse($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $identifier = $this->parse($attribute);

        if (null === $identifier) {
            return false;
        }

        $user = $token->getUser();

        /**
         * Not an administrator this plugin can read roles from. A misinstalled plugin is caught
         * at container compile time by `CheckAdminUserIsRoleAwarePass`, so reaching this line
         * means a token that has no business holding an admin permission in the first place.
         */
        if (!$user instanceof AdministrationRoleAwareInterface) {
            return false;
        }

        // An administrator with no roles is denied, not crashed: it is an ordinary state.
        if (!$this->permissions->forAdministrator($user)->allows($identifier)) {
            return false;
        }

        return $this->scopeResolver->isInScope($user, $identifier, $subject);
    }

    private function parse(string $attribute): ?PermissionIdentifier
    {
        try {
            return PermissionIdentifier::fromString($attribute);
        } catch (InvalidPermissionSyntaxException) {
            // `ROLE_*`, `IS_AUTHENTICATED_*` and the application's own attributes belong to
            // other voters. Returning null abstains and leaves those decisions alone.
            return null;
        }
    }
}
