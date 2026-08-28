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
        $container = new ContainerBuilder();

        (new OdiseoSyliusRbacExtension())->load([], $container);

        $this->assertTrue($container->hasDefinition('odiseo_rbac.event_listener.admin.menu_builder'));
        $this->assertTrue($container->hasDefinition('odiseo_rbac.form.type.administration_role'));
    }
}
