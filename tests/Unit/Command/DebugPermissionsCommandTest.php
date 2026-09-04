<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Command;

use Odiseo\SyliusRbacPlugin\Command\DebugPermissionsCommand;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\DiscoveredPermissions;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\OrphanedRouteFinder;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use Odiseo\SyliusRbacPlugin\Repository\AdministrationRoleRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class DebugPermissionsCommandTest extends TestCase
{
    public function testItListsThePermissionsWithTheirPresentationMetadata(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'), group: 'catalog'),
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.index'), group: 'sales'),
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('sylius.order.index', $output);
        self::assertStringContainsString('sylius.product.index', $output);
        self::assertStringContainsString('catalog', $output);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testItCanNarrowToOneSubject(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index')),
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.index')),
        ], ['--subject' => 'sylius.product']);

        self::assertStringContainsString('sylius.product.index', $tester->getDisplay());
        self::assertStringNotContainsString('sylius.order.index', $tester->getDisplay());
    }

    public function testItSaysSoWhenNothingMatchesTheFilters(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index')),
        ], ['--subject' => 'nothing.like_this']);

        self::assertStringContainsString('No permission matches', $tester->getDisplay());
    }

    /**
     * The reason this command exists at all: a route nothing checks has to be
     * put in front of whoever runs it, not hidden behind a flag.
     */
    public function testItPutsUnprotectedRoutesInFrontOfWhoeverRunsIt(): void
    {
        $tester = $this->runCommand(
            [new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))],
            [],
            ['some_plugin_screen' => 'declares no permission, and nothing declares one for it'],
        );

        $output = $tester->getDisplay();

        self::assertStringContainsString('Admin routes nothing checks', $output);
        self::assertStringContainsString('some_plugin_screen', $output);
        self::assertStringContainsString('reachable without any permission check', $output);
    }

    public function testItConfirmsWhenEveryAdminRouteIsAccountedFor(): void
    {
        $tester = $this->runCommand([new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))]);

        self::assertStringContainsString('Every admin route is either covered', $tester->getDisplay());
    }

    public function testPlainListingSucceedsEvenWithUnprotectedRoutesSoScriptsDoNotBreak(): void
    {
        $tester = $this->runCommand([], [], ['some_plugin_screen' => 'declares no permission']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testStrictTurnsAnUnprotectedRouteIntoAFailingBuild(): void
    {
        $tester = $this->runCommand([], ['--strict' => true], ['some_plugin_screen' => 'declares no permission']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testStrictSucceedsWhenEverythingIsCovered(): void
    {
        $tester = $this->runCommand(
            [new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))],
            ['--strict' => true],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * A rename upstream leaves the old declaration pointing at nothing -- silent unless
     * something reads the routing table back, which is exactly what this checks.
     */
    public function testItReportsADeclarationPointingAtARouteThatNoLongerExists(): void
    {
        $tester = $this->runCommand(
            [new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))],
            [],
            [],
            declaredPermissions: ['renamed_route' => 'sylius.product.update'],
        );

        $output = $tester->getDisplay();

        self::assertStringContainsString('Orphaned declarations', $output);
        self::assertStringContainsString('renamed_route', $output);
        self::assertStringContainsString('route_permissions', $output);
    }

    public function testStrictFailsOnAnOrphanedDeclaration(): void
    {
        $tester = $this->runCommand(
            [new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))],
            ['--strict' => true],
            [],
            excludedRoutes: ['renamed_route'],
        );

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testARouteListedUnderExcludedRoutesIsDescribedAsOpenByDesign(): void
    {
        $tester = $this->runCommand(
            [],
            ['route' => 'sylius_admin_login'],
            excludedRoutes: ['sylius_admin_login'],
            existingRoutes: ['sylius_admin_login'],
        );

        self::assertStringContainsString('Open by design', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testARouteNothingChecksFailsWhenAskedByName(): void
    {
        $tester = $this->runCommand([], ['route' => 'some_plugin_screen'], existingRoutes: ['some_plugin_screen']);

        self::assertStringContainsString('Nothing checks this route', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /** The route argument is a name from the router, not from the permission declarations. */
    public function testAskingAboutARouteThatDoesNotExistFailsClearly(): void
    {
        $tester = $this->runCommand([], ['route' => 'ghost_route']);

        self::assertStringContainsString('Route "ghost_route" does not exist', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /** Answers "which role covers this route", one route at a time. */
    public function testItShowsWhichRolesCoverTheRoutesRequiredPermission(): void
    {
        $catalogManager = $this->createMock(AdministrationRoleInterface::class);
        $catalogManager->method('getCode')->willReturn('catalog_manager');
        $catalogManager->method('getPermissionPatterns')->willReturn([PermissionPattern::fromString('sylius.product.*')]);

        $salesManager = $this->createMock(AdministrationRoleInterface::class);
        $salesManager->method('getCode')->willReturn('sales_manager');
        $salesManager->method('getPermissionPatterns')->willReturn([PermissionPattern::fromString('sylius.order.*')]);

        $tester = $this->runCommand(
            [],
            ['route' => 'sylius_admin_product_update'],
            declaredPermissions: ['sylius_admin_product_update' => 'sylius.product.update'],
            roles: [$catalogManager, $salesManager],
            existingRoutes: ['sylius_admin_product_update'],
        );

        $output = $tester->getDisplay();

        self::assertStringContainsString('sylius.product.update', $output);
        self::assertMatchesRegularExpression('/catalog_manager\s+\|?\s*yes/', $output);
        self::assertMatchesRegularExpression('/sales_manager\s+\|?\s*no/', $output);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @param list<PermissionDefinition> $definitions
     * @param array<string, mixed> $input
     * @param array<string, string> $unprotectedRoutes
     * @param array<string, string> $declaredPermissions
     * @param list<string> $excludedRoutes
     * @param list<AdministrationRoleInterface> $roles
     * @param list<string> $existingRoutes route names the router knows about, besides the keys of $unprotectedRoutes
     */
    private function runCommand(
        array $definitions,
        array $input = [],
        array $unprotectedRoutes = [],
        array $declaredPermissions = [],
        array $excludedRoutes = [],
        array $roles = [],
        array $existingRoutes = [],
    ): CommandTester {
        $discoverer = new class(new DiscoveredPermissions($definitions, $unprotectedRoutes)) implements PermissionDiscovererInterface {
            public function __construct(private readonly DiscoveredPermissions $result)
            {
            }

            public function discover(): DiscoveredPermissions
            {
                return $this->result;
            }
        };

        $collection = new RouteCollection();

        foreach ([...$existingRoutes, ...array_keys($unprotectedRoutes)] as $name) {
            $collection->add($name, new Route('/fake'));
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $routeMap = $this->createMock(RoutePermissionMapInterface::class);
        $routeMap->method('isExcluded')->willReturnCallback(
            static fn (string $name): bool => in_array($name, $excludedRoutes, true),
        );
        $routeMap->method('permissionFor')->willReturnCallback(
            static fn (string $name): ?string => $declaredPermissions[$name] ?? null,
        );

        $roleRepository = $this->createMock(AdministrationRoleRepositoryInterface::class);
        $roleRepository->method('findAll')->willReturn($roles);

        $tester = new CommandTester(new DebugPermissionsCommand(
            $discoverer,
            new OrphanedRouteFinder($router),
            $routeMap,
            $roleRepository,
            $router,
            $declaredPermissions,
            $excludedRoutes,
        ));
        $tester->execute($input);

        return $tester;
    }
}
