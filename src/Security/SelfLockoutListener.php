<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Sylius\Resource\Symfony\EventDispatcher\GenericEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Refuses a change that would leave the administrator making it unable to manage roles.
 *
 * Losing any other permission is recoverable: whoever still manages roles gives it back. Losing
 * the roles screen is not, because that screen is the only place inside the application where
 * roles are edited -- `odiseo:rbac:grant` is the way back.
 *
 * The question asked is "would this leave me without it?", never "was the box unticked". Roles
 * are additive, so another role of the same administrator may still grant it, and a `*.*.*` in
 * this very role covers it without naming it.
 *
 * Only the administrator's own access is guarded, and only on update. Deleting the role or
 * removing it from one's own account reach the same place and are deliberately left open.
 */
final readonly class SelfLockoutListener
{
    /** Without these the roles screen is unreachable: one lists it, the other saves it. */
    private const REQUIRED = [
        'odiseo_rbac.administration_role.index',
        'odiseo_rbac.administration_role.update',
    ];

    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function onPreUpdate(GenericEvent $event): void
    {
        $submitted = $event->getSubject();

        if (!$submitted instanceof AdministrationRoleInterface) {
            return;
        }

        $administrator = $this->administrator();

        /**
         * No administrator to lock out. The console has no token, and that is where the way back
         * from a lockout lives, so commands and fixtures must never trip on this.
         */
        if (null === $administrator) {
            return;
        }

        $lost = $this->lostBy($administrator, $submitted);

        if ([] === $lost) {
            return;
        }

        $event->stop(
            'odiseo_rbac.administration_role.cannot_revoke_own_role_management',
            GenericEvent::TYPE_ERROR,
            ['%permissions%' => implode(', ', $lost)],
        );
    }

    /**
     * Which of the required permissions the administrator would be left without.
     *
     * The union is rebuilt here rather than read from `EffectivePermissionsProviderInterface`,
     * whose per-request cache the voter has already filled with the roles as they were before
     * the form bound: asking it would answer about the state this is trying to prevent leaving.
     *
     * @return list<string>
     */
    private function lostBy(
        AdministrationRoleAwareInterface $administrator,
        AdministrationRoleInterface $submitted,
    ): array {
        $patterns = [];
        $holdsIt = false;

        /** @var AdministrationRoleInterface $held */
        foreach ($administrator->getAdministrationRoles() as $held) {
            /**
             * Matched by code rather than by object, so this holds even if the form worked on a
             * different instance of the same role than the token's user points at. Failing that
             * comparison would silently skip the check.
             */
            $isSubmitted = $held->getCode() === $submitted->getCode();
            $holdsIt = $holdsIt || $isSubmitted;

            foreach (($isSubmitted ? $submitted : $held)->getPermissionPatterns() as $pattern) {
                $patterns[$pattern->toString()] = $pattern;
            }
        }

        // Editing a role one does not hold cannot take anything away from oneself.
        if (!$holdsIt) {
            return [];
        }

        $effective = EffectivePermissions::of(array_values($patterns));
        $lost = [];

        foreach (self::REQUIRED as $identifier) {
            if (!$effective->allows(PermissionIdentifier::fromString($identifier))) {
                $lost[] = $identifier;
            }
        }

        return $lost;
    }

    private function administrator(): ?AdministrationRoleAwareInterface
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof AdministrationRoleAwareInterface ? $user : null;
    }
}
