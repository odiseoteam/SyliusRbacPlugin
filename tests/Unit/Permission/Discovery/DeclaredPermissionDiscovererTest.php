<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\DeclaredPermissionDiscoverer;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\DiscoveredPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class DeclaredPermissionDiscovererTest extends TestCase
{
    public function testItCarriesPresentationMetadataThrough(): void
    {
        $result = $this->discover([
            'sylius_admin_impersonate_user' => [
                'identifier' => 'sylius.impersonation.execute',
                'label' => 'sylius.ui.impersonate',
                'group' => 'administration',
            ],
        ], ['sylius_admin_impersonate_user']);

        self::assertCount(1, $result->definitions);

        $definition = $result->definitions[0];
        self::assertSame('sylius.impersonation.execute', $definition->identifier->toString());
        self::assertSame('sylius.ui.impersonate', $definition->label);
        self::assertSame('administration', $definition->group);
    }

    public function testMetadataIsOptional(): void
    {
        $result = $this->discover([
            'some_route' => ['identifier' => 'sylius.dashboard.view'],
        ], ['some_route']);

        self::assertNull($result->definitions[0]->label);
        self::assertNull($result->definitions[0]->group);
    }

    /**
     * A typo in one plugin's configuration must not stop the application from booting, but it
     * must not vanish either: it is reported against the route that declared it.
     */
    public function testAMalformedIdentifierIsReportedAgainstWhateverDeclaredIt(): void
    {
        $result = $this->discover([
            'broken_route' => ['identifier' => 'sylius.product'],
            'good_route' => ['identifier' => 'sylius.product.index'],
        ], ['broken_route', 'good_route']);

        self::assertCount(1, $result->definitions);
        self::assertArrayHasKey('broken_route', $result->unprotectedRoutes);
        self::assertStringContainsString('exactly 3', $result->unprotectedRoutes['broken_route']);
    }

    public function testItFindsNothingWhenNothingIsDeclared(): void
    {
        $result = $this->discover();

        self::assertSame([], $result->definitions);
        self::assertSame([], $result->unprotectedRoutes);
    }

    /**
     * The plugin that declared it was uninstalled, or Sylius renamed the route: either way the
     * permission it named no longer applies, and keeping it around would leave a phantom entry
     * in the tree for something nobody can reach any more.
     */
    public function testADeclarationWhoseRouteNoLongerExistsIsDropped(): void
    {
        $result = $this->discover([
            'sylius_mollie_admin_mollie_subscription_cancel' => ['identifier' => 'sylius_mollie.mollie_subscription.cancel'],
            'still_here' => ['identifier' => 'sylius.dashboard.view'],
        ], ['still_here']);

        self::assertSame(['sylius.dashboard.view'], array_map(
            static fn ($definition) => $definition->identifier->toString(),
            $result->definitions,
        ));
        self::assertSame([], $result->unprotectedRoutes);
    }

    /** A `Class::method` source is a code declaration, never a route -- it is never checked against the router. */
    public function testAClassMethodSourceIsNeverTreatedAsAnOrphanedRoute(): void
    {
        $result = $this->discover([
            'App\\Controller\\SomeController::someAction' => ['identifier' => 'sylius.dashboard.view'],
        ]);

        self::assertCount(1, $result->definitions);
    }

    /** @param array<string, array{identifier: string, label?: string|null, group?: string|null}> $declarations */
    private function discover(array $declarations = [], array $existingRoutes = []): DiscoveredPermissions
    {
        $collection = new RouteCollection();

        foreach ($existingRoutes as $name) {
            $collection->add($name, new Route('/admin/whatever'));
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return (new DeclaredPermissionDiscoverer($router, $declarations))->discover();
    }
}
