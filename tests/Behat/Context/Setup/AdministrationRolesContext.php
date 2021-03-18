<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Model\Permission;

final class AdministrationRolesContext implements Context
{
    /** @var FactoryInterface */
    private $administrationRoleFactory;

    /** @var RepositoryInterface */
    private $administrationRoleRepository;

    /** @var ObjectManager */
    private $administrationRoleManager;

    /** @var SharedStorageInterface */
    private $sharedStorage;

    public function __construct(
        FactoryInterface $administrationRoleFactory,
        RepositoryInterface $administrationRoleRepository,
        ObjectManager $administrationRoleManager,
        SharedStorageInterface $sharedStorage
    ) {
        $this->administrationRoleFactory = $administrationRoleFactory;
        $this->administrationRoleRepository = $administrationRoleRepository;
        $this->administrationRoleManager = $administrationRoleManager;
        $this->sharedStorage = $sharedStorage;
    }

    /**
     * @Given there is already an Administration role :name in the system
     */
    public function thereIsAlreadyAdministrationRoleInTheSystem(string $name): void
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->administrationRoleFactory->createNew();
        $administrationRole->setName($name);

        $this->administrationRoleRepository->add($administrationRole);
        $this->sharedStorage->set('administration_role', $administrationRole);
    }

    /**
     * @Given /^(this administration role) has (read|write) permission for "([^"]+)"$/
     * @Given /^(this administration role) has (read|write) permissions for "([^"]+)" and "([^"]+)"$/
     * @Given /^(this administration role) has (read|write) permissions for "([^"]+)", "([^"]+)" and "([^"]+)"$/
     * @Given /^(this administration role) has (read|write) permissions for "([^"]+)", "([^"]+)", "([^"]+)" and "([^"]+)"$/
     */
    public function thisAdministrationRoleHasAndPermissions(
        AdministrationRoleInterface $administrationRole,
        string $operationType,
        string ... $permissions
    ): void {
        $operationsType = [OperationType::read()];
        if (OperationType::WRITE === $operationType) {
            $operationsType[] = OperationType::write();
        }

        foreach ($permissions as $permission) {
            $administrationRole->addPermission(
                Permission::ofType(strtolower(str_replace(' ', '_', $permission)), $operationsType)
            );
        }

        $this->administrationRoleManager->flush();
    }
}
