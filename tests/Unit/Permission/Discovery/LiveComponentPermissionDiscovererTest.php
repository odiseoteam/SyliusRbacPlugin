<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\LiveComponentPermissionDiscoverer;
use PHPUnit\Framework\TestCase;

final class LiveComponentPermissionDiscovererTest extends TestCase
{
    public function testItReportsAComponentNothingHasAPermissionMappedFor(): void
    {
        $discoverer = new LiveComponentPermissionDiscoverer(
            ['sylius_admin:taxon:form', 'acme_plugin:widget:form'],
            ['sylius_admin:taxon:form'],
        );

        $result = $discoverer->discover();

        self::assertSame([], $result->definitions);
        self::assertArrayHasKey('acme_plugin:widget:form', $result->unprotectedRoutes);
        self::assertArrayNotHasKey('sylius_admin:taxon:form', $result->unprotectedRoutes);
    }

    public function testItStaysQuietWhenEverythingIsMapped(): void
    {
        $discoverer = new LiveComponentPermissionDiscoverer(
            ['sylius_admin:taxon:form'],
            ['sylius_admin:taxon:form'],
        );

        self::assertSame([], $discoverer->discover()->unprotectedRoutes);
    }
}
