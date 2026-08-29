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

        $routePermissions = $configs[0]['route_permissions'] ?? [];

        self::assertArrayHasKey('sylius_admin_impersonate_user', $routePermissions);
        self::assertSame('sylius.impersonation.execute', $routePermissions['sylius_admin_impersonate_user']['permission']);
        self::assertTrue($routePermissions['sylius_admin_impersonate_user']['dangerous']);
    }

    public function testTheRoutesLeftDeliberatelyOpenAreShippedToo(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new OdiseoSyliusRbacExtension());

        $extension->prepend($container);

        $publicRoutes = $container->getExtensionConfig('odiseo_sylius_rbac')[0]['public_routes'] ?? [];

        self::assertContains('sylius_admin_login', $publicRoutes);
        self::assertContains('sylius_admin_logout', $publicRoutes);
    }

    public function testDeclarationsBecomeTheParametersTheDiscoverersRead(): void
    {
        $container = $this->load([[
            'route_permissions' => [
                'some_admin_route' => ['permission' => 'sylius.thing.view', 'group' => 'administration', 'dangerous' => true],
            ],
            'public_routes' => ['some_public_route'],
        ]]);

        self::assertSame(
            ['some_admin_route' => ['identifier' => 'sylius.thing.view', 'label' => null, 'group' => 'administration', 'dangerous' => true]],
            $container->getParameter('odiseo_rbac.declared_permissions'),
        );
    }

    /**
     * Both lists feed the same parameter because a route is "handled" either way: the discoverer
     * has to stay quiet about it, or it ends up telling people to declare what they declared.
     */
    public function testBothDeclaredAndPublicRoutesCountAsHandled(): void
    {
        $container = $this->load([[
            'route_permissions' => ['some_admin_route' => ['permission' => 'sylius.thing.view']],
            'public_routes' => ['some_public_route'],
        ]]);

        self::assertSame(
            ['some_admin_route', 'some_public_route'],
            $container->getParameter('odiseo_rbac.handled_routes'),
        );
    }

    /** @param list<array<string, mixed>> $configs */
    private function load(array $configs = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new OdiseoSyliusRbacExtension())->load($configs, $container);

        return $container;
    }
}
