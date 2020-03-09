<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\CommandHandler;

use Doctrine\Common\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Command\CreateAdministrationRole;
use Odiseo\SyliusRbacPlugin\Factory\AdministrationRoleFactoryInterface;
use Odiseo\SyliusRbacPlugin\Validator\AdministrationRoleValidatorInterface;

final class CreateAdministrationRoleHandler
{
    /** @var ObjectManager */
    private $administrationRoleManager;

    /** @var AdministrationRoleFactoryInterface */
    private $administrationRoleFactory;

    /** @var AdministrationRoleValidatorInterface */
    private $validator;

    /** @var string */
    private $validationGroup;

    public function __construct(
        ObjectManager $objectManager,
        AdministrationRoleFactoryInterface $administrationRoleFactory,
        AdministrationRoleValidatorInterface $validator,
        string $validationGroup
    ) {
        $this->administrationRoleManager = $objectManager;
        $this->administrationRoleFactory = $administrationRoleFactory;
        $this->validator = $validator;
        $this->validationGroup = $validationGroup;
    }

    public function __invoke(CreateAdministrationRole $command): void
    {
        $administrationRole = $this->administrationRoleFactory->createWithNameAndPermissions(
            $command->administrationRoleName(),
            $command->permissions()
        );

        $this->validator->validate($administrationRole, $this->validationGroup);

        $this->administrationRoleManager->persist($administrationRole);
        $this->administrationRoleManager->flush();
    }
}
