<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\CommandHandler;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Factory\AdministrationRoleFactoryInterface;
use Odiseo\SyliusRbacPlugin\Message\CreateAdministrationRole;
use Odiseo\SyliusRbacPlugin\Validator\AdministrationRoleValidatorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateAdministrationRoleHandler
{
    public function __construct(
        private ObjectManager $administrationRoleManager,
        private AdministrationRoleFactoryInterface $administrationRoleFactory,
        private AdministrationRoleValidatorInterface $validator,
        private string $validationGroup,
    ) {
    }

    public function __invoke(CreateAdministrationRole $message): void
    {
        $administrationRole = $this->administrationRoleFactory->createWithNameAndPermissions(
            $message->administrationRoleName(),
            $message->permissions(),
        );

        $this->validator->validate($administrationRole, $this->validationGroup);

        $this->administrationRoleManager->persist($administrationRole);
        $this->administrationRoleManager->flush();
    }
}
