<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\LiveComponentPermissionResolver;
use PHPUnit\Framework\TestCase;

final class LiveComponentPermissionResolverTest extends TestCase
{
    public function testItResolvesAMappedComponentRegardlessOfAction(): void
    {
        $resolver = new LiveComponentPermissionResolver(['sylius_admin:taxon:form' => 'sylius.taxon.update']);

        self::assertSame('sylius.taxon.update', $resolver->resolve('sylius_admin:taxon:form', 'get'));
        self::assertSame('sylius.taxon.update', $resolver->resolve('sylius_admin:taxon:form', 'generateTaxonSlug'));
    }

    public function testItGivesUpOnAnUnmappedComponent(): void
    {
        $resolver = new LiveComponentPermissionResolver(['sylius_admin:taxon:form' => 'sylius.taxon.update']);

        self::assertNull($resolver->resolve('acme_plugin:widget:form', 'get'));
    }

    /**
     * `TreeComponent::moveUp()`/`moveDown()` reorder taxons for real; its default action only
     * renders the tree. One fixed permission would either block browsing or allow reordering to
     * anyone who can merely view it.
     */
    public function testTaxonTreeRequiresIndexToBrowseAndUpdateToReorder(): void
    {
        $resolver = new LiveComponentPermissionResolver();

        self::assertSame('sylius.taxon.index', $resolver->resolve('sylius_admin:taxon:tree', 'get'));
        self::assertSame('sylius.taxon.update', $resolver->resolve('sylius_admin:taxon:tree', 'moveUp'));
        self::assertSame('sylius.taxon.update', $resolver->resolve('sylius_admin:taxon:tree', 'moveDown'));
    }
}
