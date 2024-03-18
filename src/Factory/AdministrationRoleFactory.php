<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Factory;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Model\Permission;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class AdministrationRoleFactory implements AdministrationRoleFactoryInterface
{
    public function __construct(
        private FactoryInterface $decoratedFactory,
    ) {
    }

    public function createWithNameAndPermissions(string $name, array $permissions): AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->decoratedFactory->createNew();

        $administrationRole->setName($name);

        /**
         * @var string $permission
         * @var array $operationTypes
         */
        foreach ($permissions as $permission => $operationTypes) {
            $administrationRole->addPermission(Permission::ofType($permission, $operationTypes));
        }

        return $administrationRole;
    }

    public function createWithName(string $name): AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->decoratedFactory->createNew();

        $administrationRole->setName($name);

        return $administrationRole;
    }

    public function createNew(): AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->decoratedFactory->createNew();

        return $administrationRole;
    }
}
