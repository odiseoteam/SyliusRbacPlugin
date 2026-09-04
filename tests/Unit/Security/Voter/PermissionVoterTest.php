<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security\Voter;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Security\EffectivePermissionsProvider;
use Odiseo\SyliusRbacPlugin\Security\Scope\ScopeResolverInterface;
use Odiseo\SyliusRbacPlugin\Security\Scope\UnrestrictedScopeResolver;
use Odiseo\SyliusRbacPlugin\Security\Voter\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Odiseo\SyliusRbacPlugin\Unit\Security\RoleAwareAdministrator;

final class PermissionVoterTest extends TestCase
{
    public function testItGrantsAPermissionCoveredByOneOfTheRoles(): void
    {
        $vote = $this->vote(new RoleAwareAdministrator([['sylius.product.*']]), 'sylius.product.delete');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    public function testItDeniesAPermissionNoRoleCovers(): void
    {
        $vote = $this->vote(new RoleAwareAdministrator([['sylius.product.*']]), 'sylius.order.index');

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    /** Finding 4: this used to be a 500, from an assertion that the role was not null. */
    public function testAnAdministratorWithoutRolesIsDeniedRatherThanCrashing(): void
    {
        $vote = $this->vote(new RoleAwareAdministrator(), 'sylius.product.index');

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testAWildcardRoleGrantsEverything(): void
    {
        $administrator = new RoleAwareAdministrator([['*.*.*']]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($administrator, 'sylius.order.delete'));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($administrator, 'some_plugin.thing.execute'));
    }

    /**
     * Attributes that are not permission identifiers belong to somebody else. Abstaining leaves
     * `ROLE_*` and `IS_AUTHENTICATED_*` decisions exactly where they were.
     */
    public function testItAbstainsOnAttributesThatAreNotPermissions(): void
    {
        $administrator = new RoleAwareAdministrator([['*.*.*']]);

        foreach (['ROLE_ADMINISTRATION_ACCESS', 'IS_AUTHENTICATED_FULLY', 'sylius.product', 'EDIT'] as $attribute) {
            self::assertSame(
                VoterInterface::ACCESS_ABSTAIN,
                $this->vote($administrator, $attribute),
                sprintf('expected to abstain on "%s"', $attribute),
            );
        }
    }

    public function testAUserThatCannotHoldRolesIsDenied(): void
    {
        $vote = $this->vote(new InMemoryUser('someone', null), 'sylius.product.index');

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    /**
     * Holding the permission is necessary but not sufficient: a scope resolver that says no
     * actually denies.
     */
    public function testScopeCanRefuseSomethingThePermissionAllows(): void
    {
        $order = new \stdClass();

        $scopeResolver = new class() implements ScopeResolverInterface {
            public mixed $sawSubject = false;

            public function isInScope(
                AdministrationRoleAwareInterface $administrator,
                PermissionIdentifier $permission,
                mixed $subject,
            ): bool {
                $this->sawSubject = $subject;

                return false;
            }
        };

        $vote = $this->vote(
            new RoleAwareAdministrator([['sylius.order.*']]),
            'sylius.order.update',
            $order,
            $scopeResolver,
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
        self::assertSame($order, $scopeResolver->sawSubject, 'the subject must reach the resolver');
    }

    private function vote(
        UserInterface $user,
        string $attribute,
        mixed $subject = null,
        ?ScopeResolverInterface $scopeResolver = null,
    ): int {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new PermissionVoter(
            new EffectivePermissionsProvider(),
            $scopeResolver ?? new UnrestrictedScopeResolver(),
        );

        return $voter->vote($token, $subject, [$attribute]);
    }
}
