<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Twig;

use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionGroup;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionTreeInterface;
use Odiseo\SyliusRbacPlugin\Twig\PermissionTreeExtension;
use PHPUnit\Framework\TestCase;

final class PermissionTreeExtensionTest extends TestCase
{
    public function testItExposesTheTreeAndItsVocabularyToTheTemplate(): void
    {
        $extension = new PermissionTreeExtension($this->tree());

        self::assertSame(
            ['odiseo_rbac_permission_tree', 'odiseo_rbac_read_operations', 'odiseo_rbac_permission_identifiers'],
            array_map(static fn ($function): string => $function->getName(), $extension->getFunctions()),
        );
    }

    /** The editor needs every identifier to work out what a wildcard actually covers. */
    public function testItFlattensTheTreeIntoEveryIdentifierItHolds(): void
    {
        $extension = new PermissionTreeExtension($this->tree());

        self::assertSame(
            ['sylius.order.cancel', 'sylius.order.index', 'sylius.product.index'],
            $extension->identifiers(),
        );
    }

    private function tree(): PermissionTreeInterface
    {
        $sales = new PermissionGroup('sales');
        $sales->add(new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.index')), 'Orders');
        $sales->add(new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.cancel')), 'Orders');

        $catalog = new PermissionGroup('catalog');
        $catalog->add(new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index')), 'Products');

        return new class([$sales, $catalog]) implements PermissionTreeInterface {
            /** @param list<PermissionGroup> $groups */
            public function __construct(private readonly array $groups)
            {
            }

            public function groups(): array
            {
                return $this->groups;
            }
        };
    }
}
