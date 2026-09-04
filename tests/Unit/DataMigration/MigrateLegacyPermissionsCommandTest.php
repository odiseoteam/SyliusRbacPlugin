<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DataMigration;

use Odiseo\SyliusRbacPlugin\DataMigration\LegacyPermissionMigrator;
use Odiseo\SyliusRbacPlugin\DataMigration\LegacyRole;
use Odiseo\SyliusRbacPlugin\DataMigration\LegacySectionPermissionTranslator;
use Odiseo\SyliusRbacPlugin\DataMigration\MigrateLegacyPermissionsCommand;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class MigrateLegacyPermissionsCommandTest extends TestCase
{
    public function testItShowsWhatEachRoleWouldGetBeforeWritingAnything(): void
    {
        $repository = new InMemoryLegacyRoleRepository([
            new LegacyRole(1, 'catalog_manager', ['catalog_management' => true]),
        ]);

        $tester = $this->runCommand($repository, ['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('catalog_manager', $tester->getDisplay());
        self::assertStringContainsString('sylius.product.*', $tester->getDisplay());
        self::assertSame([], $repository->written, 'a dry run must not write');
    }

    public function testItWritesWhenNotADryRun(): void
    {
        $repository = new InMemoryLegacyRoleRepository([
            new LegacyRole(1, 'catalog_manager', ['catalog_management' => true]),
        ]);

        $tester = $this->runCommand($repository);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([1 => ['sylius.product.*']], $repository->written);
    }

    /** Running it twice must not rewrite roles that are already on the new model. */
    public function testItLeavesAlreadyMigratedRolesAlone(): void
    {
        $repository = new InMemoryLegacyRoleRepository([
            new LegacyRole(1, 'done', ['catalog_management' => true], ['sylius.taxon.*']),
        ]);

        $tester = $this->runCommand($repository);

        self::assertSame([], $repository->written);
        self::assertStringContainsString('skipped', $tester->getDisplay());
    }

    public function testOverwriteRewritesThemAnyway(): void
    {
        $repository = new InMemoryLegacyRoleRepository([
            new LegacyRole(1, 'done', ['catalog_management' => true], ['sylius.taxon.*']),
        ]);

        $this->runCommand($repository, ['--overwrite' => true]);

        self::assertSame([1 => ['sylius.product.*']], $repository->written);
    }

    /**
     * A role the command could not translate in full is the whole reason this command prints
     * anything. Exiting non-zero is what stops an upgrade script from moving on as if the data
     * had been migrated.
     */
    public function testItFailsWhenSomethingCouldNotBeTranslated(): void
    {
        $repository = new InMemoryLegacyRoleRepository([
            new LegacyRole(1, 'odd', ['loyalty_management' => true]),
        ]);

        $tester = $this->runCommand($repository);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('loyalty_management', $tester->getDisplay());
    }

    public function testItSaysSoWhenThereIsNothingToMigrate(): void
    {
        $tester = $this->runCommand(new InMemoryLegacyRoleRepository());

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No administration roles', $tester->getDisplay());
    }

    /**
     * @param array<string, bool|string> $input
     */
    private function runCommand(InMemoryLegacyRoleRepository $repository, array $input = []): CommandTester
    {
        $command = new MigrateLegacyPermissionsCommand(
            new LegacyPermissionMigrator($repository, $this->translator()),
        );

        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }

    private function translator(): LegacySectionPermissionTranslator
    {
        $collection = new RouteCollection();
        $collection->add('sylius_admin_product_index', new Route('/admin/products', [
            '_controller' => 'sylius.controller.product::indexAction',
            '_sylius' => ['permission' => true],
        ]));
        $collection->add('sylius_admin_product_update', new Route('/admin/products/{id}/edit', [
            '_controller' => 'sylius.controller.product::updateAction',
            '_sylius' => ['permission' => true],
        ]));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return new LegacySectionPermissionTranslator($router, new RoutePermissionResolver(), [
            'catalog_management' => ['sylius_admin_product'],
        ]);
    }
}
