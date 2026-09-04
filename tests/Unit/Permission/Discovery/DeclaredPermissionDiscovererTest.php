<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\DeclaredPermissionDiscoverer;
use PHPUnit\Framework\TestCase;

final class DeclaredPermissionDiscovererTest extends TestCase
{
    public function testItCarriesPresentationMetadataThrough(): void
    {
        $result = (new DeclaredPermissionDiscoverer([
            'sylius_admin_impersonate_user' => [
                'identifier' => 'sylius.impersonation.execute',
                'label' => 'sylius.ui.impersonate',
                'group' => 'administration',
            ],
        ]))->discover();

        self::assertCount(1, $result->definitions);

        $definition = $result->definitions[0];
        self::assertSame('sylius.impersonation.execute', $definition->identifier->toString());
        self::assertSame('sylius.ui.impersonate', $definition->label);
        self::assertSame('administration', $definition->group);
    }

    public function testMetadataIsOptional(): void
    {
        $result = (new DeclaredPermissionDiscoverer([
            'some_route' => ['identifier' => 'sylius.dashboard.view'],
        ]))->discover();

        self::assertNull($result->definitions[0]->label);
        self::assertNull($result->definitions[0]->group);
    }

    /**
     * A typo in one plugin's configuration must not stop the application from booting, but it
     * must not vanish either: it is reported against the route that declared it.
     */
    public function testAMalformedIdentifierIsReportedAgainstWhateverDeclaredIt(): void
    {
        $result = (new DeclaredPermissionDiscoverer([
            'broken_route' => ['identifier' => 'sylius.product'],
            'good_route' => ['identifier' => 'sylius.product.index'],
        ]))->discover();

        self::assertCount(1, $result->definitions);
        self::assertArrayHasKey('broken_route', $result->unprotectedRoutes);
        self::assertStringContainsString('exactly 3', $result->unprotectedRoutes['broken_route']);
    }

    public function testItFindsNothingWhenNothingIsDeclared(): void
    {
        $result = (new DeclaredPermissionDiscoverer())->discover();

        self::assertSame([], $result->definitions);
        self::assertSame([], $result->unprotectedRoutes);
    }
}
