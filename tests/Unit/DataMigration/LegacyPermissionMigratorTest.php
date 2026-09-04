<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DataMigration;

use Odiseo\SyliusRbacPlugin\DataMigration\LegacyPermissionMigrator;
use Odiseo\SyliusRbacPlugin\DataMigration\LegacyRole;
use Odiseo\SyliusRbacPlugin\DataMigration\LegacySectionPermissionTranslator;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class LegacyPermissionMigratorTest extends TestCase
{
    public function testItTranslatesEverySectionTheRoleHolds(): void
    {
        $migrations = $this->migrator([
            new LegacyRole(1, 'manager', ['catalog_management' => true, 'sales_management' => false]),
        ])->plan();

        self::assertCount(1, $migrations);
        self::assertSame(
            ['sylius.order.index', 'sylius.product.*'],
            $migrations[0]->patterns,
        );
    }

    /**
     * A wildcard already says everything the exact identifier says. Storing both would make the
     * grant harder to read for no gain.
     */
    /**
     * Two sections can reach the same subject through different routes — an application that
     * added its own route for a Sylius controller and filed it under a section of its own. The
     * wildcard already says everything the exact identifier says, so storing both would make
     * the grant harder to read for no gain.
     */
    public function testAnExactIdentifierCoveredByAWildcardIsDropped(): void
    {
        $withoutTheWildcard = $this->migrator([
            new LegacyRole(1, 'reader', ['legacy_product_screen' => false]),
        ])->plan();

        self::assertSame(['sylius.product.show'], $withoutTheWildcard[0]->patterns);

        $withTheWildcard = $this->migrator([
            new LegacyRole(1, 'manager', ['catalog_management' => true, 'legacy_product_screen' => false]),
        ])->plan();

        self::assertSame(['sylius.product.*'], $withTheWildcard[0]->patterns);
    }

    public function testASectionNobodyConfiguredIsReportedAndGrantsNothing(): void
    {
        $migrations = $this->migrator([
            new LegacyRole(1, 'odd', ['loyalty_management' => true]),
        ])->plan();

        self::assertTrue($migrations[0]->grantsNothing());
        self::assertCount(1, $migrations[0]->problems);
        self::assertStringContainsString('loyalty_management', $migrations[0]->problems[0]);
    }

    public function testProblemsFoundWhileReadingTheRowAreCarriedThrough(): void
    {
        $migrations = $this->migrator([
            new LegacyRole(1, 'odd', [], [], ['entry "broken" is not in the pre-v3 permission format']),
        ])->plan();

        self::assertSame(['entry "broken" is not in the pre-v3 permission format'], $migrations[0]->problems);
    }

    public function testARoleThatAlreadyHoldsPatternsIsMarkedAsSkipped(): void
    {
        $migrations = $this->migrator([
            new LegacyRole(1, 'done', ['catalog_management' => true], ['sylius.taxon.*']),
        ])->plan();

        self::assertTrue($migrations[0]->isSkipped());
    }

    public function testApplyingWritesThePatternsBack(): void
    {
        $repository = new InMemoryLegacyRoleRepository([
            new LegacyRole(9, 'manager', ['catalog_management' => true]),
        ]);

        $migrator = new LegacyPermissionMigrator($repository, $this->translator());
        $migrator->apply($migrator->plan()[0]);

        self::assertSame([9 => ['sylius.product.*']], $repository->written);
    }

    /**
     * @param list<LegacyRole> $roles
     */
    private function migrator(array $roles): LegacyPermissionMigrator
    {
        return new LegacyPermissionMigrator(new InMemoryLegacyRoleRepository($roles), $this->translator());
    }

    private function translator(): LegacySectionPermissionTranslator
    {
        $collection = new RouteCollection();

        foreach ([
            'sylius_admin_product_index' => 'sylius.controller.product::indexAction',
            'sylius_admin_product_update' => 'sylius.controller.product::updateAction',
            'sylius_admin_order_index' => 'sylius.controller.order::indexAction',
            'app_legacy_product_screen' => 'sylius.controller.product::showAction',
        ] as $name => $controller) {
            $collection->add($name, new Route('/admin/whatever', [
                '_controller' => $controller,
                '_sylius' => ['permission' => true],
            ]));
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return new LegacySectionPermissionTranslator($router, new RoutePermissionResolver(), [
            'catalog_management' => ['sylius_admin_product'],
            'sales_management' => ['sylius_admin_order'],
            'legacy_product_screen' => ['app_legacy_product_screen'],
        ]);
    }
}
