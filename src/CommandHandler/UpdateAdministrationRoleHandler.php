<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\CommandHandler;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Message\UpdateAdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Factory\AdministrationRoleFactoryInterface;
use Odiseo\SyliusRbacPlugin\Model\PermissionInterface;
use Odiseo\SyliusRbacPlugin\Validator\AdministrationRoleValidatorInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class UpdateAdministrationRoleHandler implements MessageHandlerInterface
{
    public function __construct(
        private ObjectManager $administrationRoleManager,
        private AdministrationRoleFactoryInterface $administrationRoleFactory,
        private RepositoryInterface $administrationRoleRepository,
        private AdministrationRoleValidatorInterface $validator,
        private string $validationGroup
    ) {
    }

    public function __invoke(UpdateAdministrationRole $message): void
    {
        /** @var AdministrationRoleInterface|null $administrationRole */
        $administrationRole = $this
            ->administrationRoleRepository
            ->find($message->administrationRoleId())
        ;

        if (null === $administrationRole) {
            throw new \InvalidArgumentException('odiseo_sylius_rbac_plugin.administration_role_does_not_exist');
        }

        $administrationRoleUpdates = $this->administrationRoleFactory->createWithNameAndPermissions(
            $message->administrationRoleName(),
            $message->permissions()
        );

        $this->validator->validate($administrationRoleUpdates, $this->validationGroup);

        $administrationRole->setName($administrationRoleUpdates->getName());
        $administrationRole->clearPermissions();

        /** @var PermissionInterface $permission */
        foreach ($administrationRoleUpdates->getPermissions() as $permission) {
            $administrationRole->addPermission($permission);
        }

        $this->administrationRoleManager->flush();
    }
}
