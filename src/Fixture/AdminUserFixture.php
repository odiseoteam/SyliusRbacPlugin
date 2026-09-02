<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AdminUserFixture as BaseAdminUserFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class AdminUserFixture extends BaseAdminUserFixture
{
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $node = $resourceNode->children();

        $node->arrayNode('administration_roles')->scalarPrototype()->cannotBeEmpty();

        parent::configureResourceNode($resourceNode);
    }
}
