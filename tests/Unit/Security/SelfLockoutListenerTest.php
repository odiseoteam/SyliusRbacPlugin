<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Security\SelfLockoutListener;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Symfony\EventDispatcher\GenericEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SelfLockoutListenerTest extends TestCase
{
    public function testItStopsAnAdministratorFromRevokingRoleManagementFromTheirOwnRole(): void
    {
        $role = $this->role('super_admin', ['sylius.product.*']);
        $event = new GenericEvent($role);

        $this->listener($this->administrator($role))->onPreUpdate($event);

        self::assertTrue($event->isStopped());
        self::assertSame(
            'odiseo_rbac.administration_role.cannot_revoke_own_role_management',
            $event->getMessage(),
        );
    }

    /** A wildcard grants it without naming it, so reading the stored patterns is not enough. */
    public function testItAllowsAWildcardThatStillCoversRoleManagement(): void
    {
        $role = $this->role('super_admin', ['odiseo_rbac.administration_role.*']);
        $event = new GenericEvent($role);

        $this->listener($this->administrator($role))->onPreUpdate($event);

        self::assertFalse($event->isStopped());
    }

    /** Roles are additive: another one of the administrator's may still grant it. */
    public function testItAllowsRevokingWhenAnotherRoleOfTheSameAdministratorGrantsIt(): void
    {
        $edited = $this->role('super_admin', ['sylius.product.*']);
        $other = $this->role('catalog', ['odiseo_rbac.administration_role.*']);
        $event = new GenericEvent($edited);

        $this->listener($this->administrator($edited, $other))->onPreUpdate($event);

        self::assertFalse($event->isStopped());
    }

    public function testItLeavesARoleTheAdministratorDoesNotHoldAlone(): void
    {
        $edited = $this->role('read_only', []);
        $event = new GenericEvent($edited);

        $this->listener($this->administrator($this->role('super_admin', ['*.*.*'])))->onPreUpdate($event);

        self::assertFalse($event->isStopped());
    }

    /**
     * The console has no token, and that is where the way back from a lockout lives: a command
     * or a fixture must never trip on this.
     */
    public function testItDoesNothingWithoutAnAuthenticatedAdministrator(): void
    {
        $event = new GenericEvent($this->role('super_admin', []));

        (new SelfLockoutListener(new TokenStorage(), $this->createMock(TranslatorInterface::class)))->onPreUpdate($event);

        self::assertFalse($event->isStopped());
    }

    public function testItIgnoresASubjectThatIsNotAnAdministrationRole(): void
    {
        $event = new GenericEvent(new \stdClass());

        $this->listener($this->administrator($this->role('super_admin', [])))->onPreUpdate($event);

        self::assertFalse($event->isStopped());
    }

    /**
     * The form may hand back a different instance of the role than the token's user points at.
     * Comparing by object would silently skip the check; the submitted patterns must win.
     */
    public function testItComparesTheEditedRoleByCodeRatherThanByIdentity(): void
    {
        $held = $this->role('super_admin', ['*.*.*']);
        $submitted = $this->role('super_admin', ['sylius.product.*']);
        $event = new GenericEvent($submitted);

        $this->listener($this->administrator($held))->onPreUpdate($event);

        self::assertTrue($event->isStopped());
    }

    /** Keeping only `index` still leaves the screen unusable: nothing can be saved from it. */
    public function testItStopsWhenOnlyOneOfTheTwoRequiredPermissionsSurvives(): void
    {
        $role = $this->role('super_admin', ['odiseo_rbac.administration_role.index']);
        $event = new GenericEvent($role);

        $this->listener($this->administrator($role))->onPreUpdate($event);

        self::assertTrue($event->isStopped());
    }

    /**
     * The raw identifier means nothing to an administrator who has never toggled "Show
     * identifiers" -- the message names what would be lost in words instead.
     */
    public function testTheMessageNamesWhatWouldBeLostInWordsRatherThanByIdentifier(): void
    {
        $role = $this->role('super_admin', ['odiseo_rbac.administration_role.index']);
        $event = new GenericEvent($role);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->with('odiseo_rbac.ui.administration_role_operation.update')
            ->willReturn('edit roles');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->administrator($role));
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        (new SelfLockoutListener($storage, $translator))->onPreUpdate($event);

        self::assertSame(['%permissions%' => 'edit roles'], $event->getMessageParameters());
    }

    /** @param list<string> $permissions */
    private function role(string $code, array $permissions): AdministrationRoleInterface
    {
        $role = new AdministrationRole();
        $role->setCode($code);
        $role->setPermissions($permissions);

        return $role;
    }

    private function administrator(AdministrationRoleInterface ...$roles): AdministrationRoleAwareInterface
    {
        $administrator = new class() implements AdministrationRoleAwareInterface, UserInterface {
            use AdministrationRoleAwareTrait;

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'ada';
            }
        };

        foreach ($roles as $role) {
            $administrator->addAdministrationRole($role);
        }

        return $administrator;
    }

    private function listener(AdministrationRoleAwareInterface $administrator): SelfLockoutListener
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($administrator);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SelfLockoutListener($storage, $translator);
    }
}
