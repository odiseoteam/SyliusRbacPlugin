<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DependencyInjection\Compiler;

use Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler\CollectLiveComponentsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CollectLiveComponentsPassTest extends TestCase
{
    public function testItCollectsTheKeyOfEveryTaggedLiveComponent(): void
    {
        $container = new ContainerBuilder();
        $container->register('sylius_admin.twig.component.taxon.form', \stdClass::class)
            ->addTag('sylius.live_component.admin', ['key' => 'sylius_admin:taxon:form']);
        $container->register('sylius_admin.twig.component.taxon.tree', \stdClass::class)
            ->addTag('sylius.live_component.admin', ['key' => 'sylius_admin:taxon:tree']);

        (new CollectLiveComponentsPass())->process($container);

        self::assertSame(
            ['sylius_admin:taxon:form', 'sylius_admin:taxon:tree'],
            $container->getParameter('odiseo_rbac.live_components'),
        );
    }

    public function testItStaysQuietWhenNothingIsTagged(): void
    {
        $container = new ContainerBuilder();

        (new CollectLiveComponentsPass())->process($container);

        self::assertSame([], $container->getParameter('odiseo_rbac.live_components'));
    }

    /** A tag without a `key` names nothing this plugin could check a permission against. */
    public function testItIgnoresATagWithNoKey(): void
    {
        $container = new ContainerBuilder();
        $container->register('some_service', \stdClass::class)
            ->addTag('sylius.live_component.admin');

        (new CollectLiveComponentsPass())->process($container);

        self::assertSame([], $container->getParameter('odiseo_rbac.live_components'));
    }
}
