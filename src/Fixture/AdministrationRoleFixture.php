<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Model\Permission;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class AdministrationRoleFixture extends AbstractFixture implements FixtureInterface
{
    public function __construct(
        protected FactoryInterface $administrationRoleFactory,
        protected ObjectManager $administrationRoleManager,
    ) {
    }

    public function load(array $options): void
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->administrationRoleFactory->createNew();

        $administrationRole->setName($options['name']);

        foreach ($options['permissions'] as $permissionName) {
            $administrationRole
                ->addPermission(Permission::ofType($permissionName, [OperationType::read(), OperationType::write()]))
            ;
        }

        $this->administrationRoleManager->persist($administrationRole);
        $this->administrationRoleManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $node = $optionsNode->children();

        $node->scalarNode('name')->cannotBeEmpty();
        $node->arrayNode('permissions')->scalarPrototype()->defaultValue([]);
    }

    public function getName(): string
    {
        return 'administration_role';
    }
}
