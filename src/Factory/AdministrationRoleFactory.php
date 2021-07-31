<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Factory;

use Sylius\Component\Resource\Factory\FactoryInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Model\Permission;

final class AdministrationRoleFactory implements AdministrationRoleFactoryInterface
{
    /** @var FactoryInterface */
    private $decoratedFactory;

    public function __construct(FactoryInterface $decoratedFactory)
    {
        $this->decoratedFactory = $decoratedFactory;
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

    public function createNew(): AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->decoratedFactory->createNew();

        return $administrationRole;
    }
}
