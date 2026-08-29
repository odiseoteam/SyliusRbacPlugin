<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security;

use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Security\EffectivePermissionsProvider;
use PHPUnit\Framework\TestCase;

final class EffectivePermissionsProviderTest extends TestCase
{
    /**
     * The reason the model moved to many-to-many. Under the pre-v3 one-role-per-administrator
     * rule a shop had to create a combinatorial role per person instead of composing two roles
     * it already had.
     */
    public function testPermissionsAreTheUnionOfEveryRole(): void
    {
        $administrator = new RoleAwareAdministrator([
            ['sylius.product.*'],
            ['sylius.order.index', 'sylius.order.show'],
        ]);

        $permissions = (new EffectivePermissionsProvider())->forAdministrator($administrator);

        self::assertTrue($permissions->allows(PermissionIdentifier::fromString('sylius.product.delete')));
        self::assertTrue($permissions->allows(PermissionIdentifier::fromString('sylius.order.index')));
        self::assertFalse($permissions->allows(PermissionIdentifier::fromString('sylius.order.delete')));
    }

    public function testAPatternHeldByTwoRolesIsStoredOnce(): void
    {
        $administrator = new RoleAwareAdministrator([
            ['sylius.product.*', 'sylius.taxon.*'],
            ['sylius.product.*'],
        ]);

        self::assertSame(
            ['sylius.product.*', 'sylius.taxon.*'],
            (new EffectivePermissionsProvider())->forAdministrator($administrator)->toStrings(),
        );
    }

    /** Finding 4: an administrator with no roles is an ordinary state, not an error. */
    public function testAnAdministratorWithoutRolesAllowsNothingAndDoesNotFail(): void
    {
        $permissions = (new EffectivePermissionsProvider())->forAdministrator(new RoleAwareAdministrator());

        self::assertTrue($permissions->isEmpty());
        self::assertFalse($permissions->allows(PermissionIdentifier::fromString('sylius.product.index')));
    }

    public function testTheUnionIsComputedOncePerAdministrator(): void
    {
        $provider = new EffectivePermissionsProvider();
        $administrator = new RoleAwareAdministrator([['sylius.product.*']]);

        self::assertSame(
            $provider->forAdministrator($administrator),
            $provider->forAdministrator($administrator),
        );
    }

    /**
     * Two administrators must never share an entry. A plain array keyed by anything reusable
     * would serve the first user of a long-running worker to every later one.
     */
    public function testTwoAdministratorsDoNotShareTheCache(): void
    {
        $provider = new EffectivePermissionsProvider();

        $first = $provider->forAdministrator(new RoleAwareAdministrator([['sylius.product.*']]));
        $second = $provider->forAdministrator(new RoleAwareAdministrator([['sylius.order.*']]));

        self::assertSame(['sylius.product.*'], $first->toStrings());
        self::assertSame(['sylius.order.*'], $second->toStrings());
    }
}
