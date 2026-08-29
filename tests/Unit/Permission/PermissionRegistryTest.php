<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\UnknownPermissionException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistry;
use PHPUnit\Framework\TestCase;

final class PermissionRegistryTest extends TestCase
{
    public function testItOrdersPermissionsByIdentifierSoOutputIsStable(): void
    {
        $registry = new PermissionRegistry([
            self::definition('sylius.product.update'),
            self::definition('odiseo_rbac.administration_role.index'),
            self::definition('sylius.product.index'),
        ]);

        self::assertSame([
            'odiseo_rbac.administration_role.index',
            'sylius.product.index',
            'sylius.product.update',
        ], array_keys($registry->all()));
    }

    public function testItAnswersWhetherAPermissionExists(): void
    {
        $registry = new PermissionRegistry([self::definition('sylius.product.update')]);

        self::assertTrue($registry->has(PermissionIdentifier::fromString('sylius.product.update')));
        self::assertFalse($registry->has(PermissionIdentifier::fromString('sylius.product.delete')));
    }

    /**
     * An unknown permission is a bug in the caller, not a denial. Returning "false" here would
     * bury it.
     */
    public function testItRefusesToInventAPermissionItWasNeverGiven(): void
    {
        $registry = new PermissionRegistry();

        $this->expectException(UnknownPermissionException::class);
        $this->expectExceptionMessageMatches('/sylius\.product\.update/');

        $registry->get(PermissionIdentifier::fromString('sylius.product.update'));
    }

    public function testDiscoveryAndDeclarationDescribeTheSamePermissionTogether(): void
    {
        $registry = new PermissionRegistry([
            // What discovery knows: the permission exists.
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.update')),
            // What the attribute adds: what to call it and where it belongs.
            new PermissionDefinition(
                PermissionIdentifier::fromString('sylius.product.update'),
                label: 'sylius.ui.edit_product',
                group: 'catalog',
            ),
        ]);

        $definition = $registry->get(PermissionIdentifier::fromString('sylius.product.update'));

        self::assertCount(1, $registry->all());
        self::assertSame('sylius.ui.edit_product', $definition->label);
        self::assertSame('catalog', $definition->group);
    }

    public function testMergingNeverUnflagsSomethingDangerous(): void
    {
        $registry = new PermissionRegistry([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.impersonation.execute'), dangerous: true),
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.impersonation.execute'), group: 'administration'),
        ]);

        $definition = $registry->get(PermissionIdentifier::fromString('sylius.impersonation.execute'));

        self::assertTrue($definition->dangerous);
        self::assertSame('administration', $definition->group);
    }

    public function testItExpandsAPatternIntoWhatItActuallyCovers(): void
    {
        $registry = new PermissionRegistry([
            self::definition('sylius.product.index'),
            self::definition('sylius.product.update'),
            self::definition('sylius.product_variant.update'),
            self::definition('sylius.order.index'),
        ]);

        self::assertSame(
            ['sylius.product.index', 'sylius.product.update'],
            array_keys($registry->matching(PermissionPattern::fromString('sylius.product.*'))),
        );

        self::assertSame(
            ['sylius.order.index', 'sylius.product.index'],
            array_keys($registry->matching(PermissionPattern::fromString('*.*.index'))),
        );

        self::assertCount(4, $registry->matching(PermissionPattern::any()));
    }

    /**
     * A pattern matching nothing means a role points at something that no longer exists — a
     * resource some plugin removed. The registry has to be able to say so.
     */
    public function testAPatternCanCoverNothing(): void
    {
        $registry = new PermissionRegistry([self::definition('sylius.product.index')]);

        self::assertSame([], $registry->matching(PermissionPattern::fromString('sylius.vendor.*')));
    }

    public function testMergingDifferentPermissionsIsAProgrammingError(): void
    {
        $this->expectException(\LogicException::class);

        self::definition('sylius.product.index')->mergedWith(self::definition('sylius.order.index'));
    }

    private static function definition(string $identifier): PermissionDefinition
    {
        return new PermissionDefinition(PermissionIdentifier::fromString($identifier));
    }
}
