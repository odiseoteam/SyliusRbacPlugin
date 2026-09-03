<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\ApiOperationPermissionDiscoverer;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\DiscoveredPermissions;
use Odiseo\SyliusRbacPlugin\Security\Api\ApiOperationPermissionResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class ApiOperationPermissionDiscovererTest extends TestCase
{
    /**
     * The admin API reaches records the admin screens do not: sub-resources with no screen of
     * their own, and `show` for resources whose CRUD declares `except: ['show']`. Those
     * permissions are checked at runtime either way, so leaving them out of the vocabulary makes
     * them ungrantable rather than unnecessary.
     */
    public function testItNamesThePermissionsOnlyTheApiAsksFor(): void
    {
        $discovered = $this->discover(
            ['sylius_api_admin_province_get' => $this->operation('/api/v2/admin/provinces/{id}')],
            'sylius.province.show',
        );

        self::assertSame([], $discovered->unprotectedRoutes);
        self::assertSame(
            ['sylius.province.show'],
            array_map(
                static fn ($definition): string => $definition->identifier->toString(),
                $discovered->definitions,
            ),
        );
    }

    public function testItIgnoresEverythingOutsideTheAdminApi(): void
    {
        $discovered = $this->discover([
            'sylius_api_shop_product_get' => $this->operation('/api/v2/shop/products/{code}'),
            'sylius_admin_product_index' => $this->operation('/admin/products/'),
        ], 'sylius.product.show');

        self::assertEquals(new DiscoveredPermissions(), $discovered);
    }

    /**
     * A route already declared or left open on purpose is passed over rather than reported: the
     * report exists to name what needs attention, and telling someone to declare what they
     * declared is how a coverage tool gets ignored.
     */
    public function testItSaysNothingAboutRoutesAlreadyAccountedFor(): void
    {
        $discovered = $this->discover(
            ['sylius_api_admin_statistics' => $this->plainController('/api/v2/admin/statistics')],
            null,
            ['sylius_api_admin_statistics'],
        );

        self::assertEquals(new DiscoveredPermissions(), $discovered);
    }

    public function testAPlainControllerNobodyDeclaredIsReported(): void
    {
        $discovered = $this->discover(
            ['sylius_api_admin_custom' => $this->plainController('/api/v2/admin/custom')],
            null,
        );

        self::assertArrayHasKey('sylius_api_admin_custom', $discovered->unprotectedRoutes);
        self::assertSame([], $discovered->definitions);
    }

    public function testAnOperationNothingCanNameIsReported(): void
    {
        $discovered = $this->discover(
            ['sylius_api_admin_odd' => $this->operation('/api/v2/admin/odd')],
            null,
        );

        self::assertArrayHasKey('sylius_api_admin_odd', $discovered->unprotectedRoutes);
    }

    /**
     * @param array<string, Route> $routes
     * @param list<string> $handledRoutes
     */
    private function discover(array $routes, ?string $permission, array $handledRoutes = []): DiscoveredPermissions
    {
        $collection = new RouteCollection();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        $resolver = $this->createMock(ApiOperationPermissionResolverInterface::class);
        $resolver->method('resolve')->willReturn($permission);

        return (new ApiOperationPermissionDiscoverer($router, $resolver, $handledRoutes))->discover();
    }

    private function operation(string $path): Route
    {
        return new Route($path, [
            '_api_resource_class' => 'App\\Resource',
            '_api_operation_name' => 'an_operation',
        ]);
    }

    private function plainController(string $path): Route
    {
        return new Route($path, ['_controller' => 'App\\Controller::index']);
    }
}
