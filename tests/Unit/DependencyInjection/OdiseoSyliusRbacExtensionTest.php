<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DependencyInjection;

use Odiseo\SyliusRbacPlugin\DependencyInjection\OdiseoSyliusRbacExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OdiseoSyliusRbacExtensionTest extends TestCase
{
    public function testItLoadsThePluginServicesFromTheRootConfigDirectory(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition('odiseo_rbac.event_listener.admin.menu_builder'));
        self::assertTrue($container->hasDefinition('odiseo_rbac.form.type.administration_role'));
        self::assertTrue($container->hasDefinition('odiseo_rbac.permission.registry'));
        self::assertTrue($container->hasDefinition('odiseo_rbac.command.debug_permissions'));
    }

    /**
     * The declarations are prepended by the extension rather than left to the application's
     * imports. Everything else the plugin ships degrades visibly when `config/config.yaml` is
     * not imported; these are the only thing standing between an administrator and the
     * impersonation endpoint, so they cannot be optional.
     */
    public function testItShipsTheRoutePermissionDeclarationsWithoutTheApplicationImportingAnything(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new OdiseoSyliusRbacExtension());

        $extension->prepend($container);

        $configs = $container->getExtensionConfig('odiseo_sylius_rbac');

        self::assertNotSame([], $configs, 'the extension prepended no configuration at all');

        $routePermissions = self::prependedKey($container, 'route_permissions');

        self::assertArrayHasKey('sylius_admin_impersonate_user', $routePermissions);
        self::assertSame('sylius.impersonation.execute', $routePermissions['sylius_admin_impersonate_user']['permission']);
    }

    public function testTheRoutesLeftDeliberatelyOpenAreShippedToo(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new OdiseoSyliusRbacExtension());

        $extension->prepend($container);

        $excludedRoutes = self::prependedKey($container, 'excluded_routes');

        self::assertContains('sylius_admin_login', $excludedRoutes);
        self::assertContains('sylius_admin_logout', $excludedRoutes);
    }

    public function testDeclarationsBecomeTheParametersTheDiscoverersRead(): void
    {
        $container = $this->load([[
            'route_permissions' => [
                'some_admin_route' => ['permission' => 'sylius.thing.view', 'group' => 'administration'],
            ],
            'excluded_routes' => ['some_excluded_route'],
        ]]);

        self::assertSame(
            ['some_admin_route' => ['identifier' => 'sylius.thing.view', 'label' => null, 'group' => 'administration']],
            $container->getParameter('odiseo_rbac.declared_permissions'),
        );
    }

    /**
     * Both lists feed the same parameter because a route is "handled" either way: the discoverer
     * has to stay quiet about it, or it ends up telling people to declare what they declared.
     */
    public function testBothDeclaredAndExcludedRoutesCountAsHandled(): void
    {
        $container = $this->load([[
            'route_permissions' => ['some_admin_route' => ['permission' => 'sylius.thing.view']],
            'excluded_routes' => ['some_excluded_route'],
        ]]);

        self::assertSame(
            ['some_admin_route', 'some_excluded_route', 'sylius_admin_entity_autocomplete', 'sylius_admin_live_component'],
            $container->getParameter('odiseo_rbac.handled_routes'),
        );
    }

    /**
     * A route only some installations ship is declared with the package that owns it. Without
     * the package the route is not there either, so keeping the declaration would mean carrying
     * a permission nothing can reach and reporting it orphaned on every debug run.
     */
    public function testADeclarationIsDroppedWhenThePackageItNamesIsNotInstalled(): void
    {
        $container = $this->load([[
            'route_permissions' => [
                'route_from_a_plugin_nobody_installed' => [
                    'permission' => 'some_vendor.thing.view',
                    'package' => 'some-vendor/a-plugin-that-is-not-installed',
                ],
            ],
        ]]);

        self::assertSame([], $container->getParameter('odiseo_rbac.declared_permissions'));
        self::assertNotContains(
            'route_from_a_plugin_nobody_installed',
            $container->getParameter('odiseo_rbac.handled_routes'),
        );
    }

    /** Absence is the only thing that excuses it: an installed package is checked like any other. */
    public function testADeclarationIsKeptWhenThePackageItNamesIsInstalled(): void
    {
        $container = $this->load([[
            'route_permissions' => [
                'route_from_an_installed_plugin' => [
                    'permission' => 'sylius.thing.view',
                    'package' => 'sylius/sylius',
                ],
            ],
        ]]);

        self::assertArrayHasKey(
            'route_from_an_installed_plugin',
            $container->getParameter('odiseo_rbac.declared_permissions'),
        );
    }

    /**
     * All of them, not any. A route one plugin registers only while a second one is installed is
     * gone as soon as either leaves, so a declaration naming two packages needs both.
     */
    public function testADeclarationNamingSeveralPackagesNeedsEveryOneOfThem(): void
    {
        $container = $this->load([[
            'route_permissions' => [
                'route_from_an_integration_between_two_plugins' => [
                    'permission' => 'sylius.thing.view',
                    'package' => ['sylius/sylius', 'some-vendor/a-plugin-that-is-not-installed'],
                ],
            ],
        ]]);

        self::assertSame([], $container->getParameter('odiseo_rbac.declared_permissions'));
    }

    /**
     * A schema default is dropped the moment the node is set anywhere: declaring one section used
     * to lose `rbac`, and redeclaring a Sylius section lost the other four.
     */
    public function testAnApplicationDeclaringItsOwnLegacySectionKeepsTheOnesThePluginShips(): void
    {
        $sections = $this->loadWithPrepended([[
            'custom_sections' => ['unified_orders' => ['app_component_unified_orders']],
        ]])->getParameter('odiseo_rbac.legacy_section_routes');

        self::assertArrayHasKey('unified_orders', $sections);
        self::assertArrayHasKey('rbac', $sections);
        self::assertArrayHasKey('catalog_management', $sections);
        self::assertArrayHasKey('sales_management', $sections);
    }

    /**
     * Prefixes are added to the section, not replaced: the roles being migrated were granted over
     * both halves.
     */
    public function testPrefixesAnApplicationAddsToASyliusSectionJoinTheOnesAlreadyThere(): void
    {
        $sections = $this->loadWithPrepended([[
            'sylius_sections' => ['sales_management' => ['app_admin_dispatch']],
        ]])->getParameter('odiseo_rbac.legacy_section_routes');

        self::assertContains('sylius_admin_order', $sections['sales_management']);
        self::assertContains('app_admin_dispatch', $sections['sales_management']);
    }

    /**
     * As an application goes through it: the prepended files first, its own config on top.
     *
     * @param list<array<string, mixed>> $configs
     */
    private function loadWithPrepended(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new OdiseoSyliusRbacExtension());

        $extension->prepend($container);
        $extension->load(
            [...$container->getExtensionConfig('odiseo_sylius_rbac'), ...$configs],
            $container,
        );

        return $container;
    }

    /**
     * One key across everything prepended; which index a file lands at is an implementation detail.
     *
     * @return array<mixed>
     */
    private static function prependedKey(ContainerBuilder $container, string $key): array
    {
        $values = [];

        foreach ($container->getExtensionConfig('odiseo_sylius_rbac') as $config) {
            $values = [...$values, ...($config[$key] ?? [])];
        }

        return $values;
    }

    /** @param list<array<string, mixed>> $configs */
    private function load(array $configs = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new OdiseoSyliusRbacExtension())->load($configs, $container);

        return $container;
    }
}
