<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\ResourceRoutePermissionDiscoverer;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class ResourceRoutePermissionDiscovererTest extends TestCase
{
    public function testItDerivesIdentifiersFromResourceControllerRoutes(): void
    {
        $result = $this->discover([
            'sylius_admin_product_index' => self::route('sylius.controller.product::indexAction'),
            'sylius_admin_product_create' => self::route('sylius.controller.product::createAction'),
            'sylius_admin_product_bulk_delete' => self::route('sylius.controller.product::bulkDeleteAction'),
            'odiseo_rbac_admin_administration_role_update' => self::route(
                'odiseo_rbac.controller.administration_role::updateAction',
            ),
        ]);

        self::assertSame([
            'sylius.product.index',
            'sylius.product.create',
            'sylius.product.bulk_delete',
            'odiseo_rbac.administration_role.update',
        ], self::identifiers($result->definitions));

        self::assertSame([], $result->unprotectedRoutes);
    }

    /**
     * The case that matters to whoever installs a plugin with its own controllers, or adds an
     * admin route of their own: without this the route is simply invisible, and the tool stays
     * quiet at exactly the moment it should speak up.
     */
    public function testAnAdminRouteThatChecksNothingIsReportedRatherThanIgnored(): void
    {
        $result = $this->discover([
            'some_plugin_admin_screen' => new Route('/admin/some-plugin/dashboard', [
                '_controller' => 'App\\Controller\\SomePluginController::indexAction',
            ]),
        ]);

        self::assertSame([], $result->definitions);
        self::assertArrayHasKey('some_plugin_admin_screen', $result->unprotectedRoutes);
        self::assertStringContainsString('declares no permission', $result->unprotectedRoutes['some_plugin_admin_screen']);
    }

    /** Routes outside the admin are none of this plugin's business. */
    public function testItLooksOnlyAtTheAdmin(): void
    {
        $result = $this->discover([
            'sylius_shop_homepage' => new Route('/', ['_controller' => 'App\\Controller\\Home::indexAction']),
            'api_products' => new Route('/api/v2/admin/products', ['_controller' => 'api_platform.action.get_collection']),
        ]);

        self::assertSame([], $result->definitions);
        self::assertSame([], $result->unprotectedRoutes);
    }

    public function testARouteDeclaredPublicIsNotReported(): void
    {
        $result = $this->discover(
            ['sylius_admin_login' => new Route('/admin/login', ['_controller' => 'sylius.security::loginAction'])],
            ['sylius_admin_login'],
        );

        self::assertSame([], $result->unprotectedRoutes);
    }

    /**
     * Sylius checks UPDATE for all of these, so a role that can edit an order can also cancel
     * it. Encoded here because it is the reason workflow transitions need permissions of their own.
     */
    public function testActionsThatSyliusCollapsesIntoUpdateAreMappedToUpdate(): void
    {
        $result = $this->discover([
            'sylius_admin_order_cancel' => self::route('sylius.controller.order::applyStateMachineTransitionAction'),
            'sylius_admin_product_variant_positions' => self::route(
                'sylius.controller.product_variant::updatePositionsAction',
            ),
        ]);

        self::assertSame(['sylius.order.update', 'sylius.product_variant.update'], self::identifiers($result->definitions));
    }

    public function testTheSamePermissionReachedBySeveralRoutesIsEmittedOnceperRoute(): void
    {
        $result = $this->discover([
            'sylius_admin_product_create_get' => self::route('sylius.controller.product::createAction'),
            'sylius_admin_product_create_post' => self::route('sylius.controller.product::createAction'),
        ]);

        // Deduplication is the registry's job, so both are emitted and merge later.
        self::assertSame(['sylius.product.create', 'sylius.product.create'], self::identifiers($result->definitions));
    }

    /**
     * A route asking for a permission nobody can name is the exact hole this plugin closes, so
     * it is reported. It must not throw either: one odd third-party controller cannot be
     * allowed to stop the application from booting.
     *
     * @dataProvider unmappableRoutes
     */
    public function testItReportsRoutesItCannotMapInsteadOfDroppingOrThrowing(string $controller, string $expected): void
    {
        $result = $this->discover(['some_route' => self::route($controller)]);

        self::assertSame([], $result->definitions);
        self::assertArrayHasKey('some_route', $result->unprotectedRoutes);
        self::assertStringContainsString($expected, $result->unprotectedRoutes['some_route']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function unmappableRoutes(): iterable
    {
        yield 'invokable controller' => ['App\Controller\DeleteCatalogPromotion', 'not a "service::action" pair'];
        yield 'unknown action' => ['sylius.controller.promotion_coupon::generateAction', 'not a known resource action'];
        yield 'unknown action on a non-resource service' => ['sylius.security::loginAction', 'not a known resource action'];
        yield 'known action on a non-resource service' => ['sylius.security::indexAction', 'does not look like'];
        yield 'uppercase service' => ['Sylius.controller.Product::indexAction', 'valid permission identifier'];
    }

    /**
     * Telling someone to declare a route they already declared trains them to ignore the
     * warning, and the warning is the only thing standing between an uncovered route and
     * nobody noticing.
     */
    public function testRoutesAlreadyCoveredByADeclarationArePassedOverInSilence(): void
    {
        $routes = [
            'sylius_admin_promotion_coupon_generate' => self::route('sylius.controller.promotion_coupon::generateAction'),
            'sylius_admin_login' => self::route('sylius.security::loginAction'),
        ];

        self::assertCount(2, $this->discover($routes)->unprotectedRoutes);

        $result = $this->discover($routes, ['sylius_admin_promotion_coupon_generate', 'sylius_admin_login']);

        self::assertSame([], $result->unprotectedRoutes);
        self::assertSame([], $result->definitions);
    }

    /**
     * @param array<string, Route> $routes
     * @param list<string> $handledRoutes
     */
    private function discover(array $routes, array $handledRoutes = []): \Odiseo\SyliusRbacPlugin\Permission\Discovery\DiscoveredPermissions
    {
        $collection = new RouteCollection();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return (new ResourceRoutePermissionDiscoverer($router, new RoutePermissionResolver(), $handledRoutes))->discover();
    }

    private static function route(string $controller, bool $permission = true): Route
    {
        return new Route('/admin/whatever', [
            '_controller' => $controller,
            '_sylius' => ['permission' => $permission],
        ]);
    }

    /**
     * @param list<PermissionDefinition> $definitions
     *
     * @return list<string>
     */
    private static function identifiers(array $definitions): array
    {
        return array_map(
            static fn (PermissionDefinition $definition): string => $definition->identifier->toString(),
            $definitions,
        );
    }
}
