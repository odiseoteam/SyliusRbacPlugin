<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\CommandHandler;

use Doctrine\Common\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Message\UpdateAdministrationRole;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Factory\AdministrationRoleFactoryInterface;
use Odiseo\SyliusRbacPlugin\Model\PermissionInterface;
use Odiseo\SyliusRbacPlugin\Validator\AdministrationRoleValidatorInterface;

final class UpdateAdministrationRoleHandler
{
    /** @var ObjectManager */
    private $administrationRoleManager;

    /** @var AdministrationRoleFactoryInterface */
    private $administrationRoleFactory;

    /** @var RepositoryInterface */
    private $administrationRoleRepository;

    /** @var AdministrationRoleValidatorInterface */
    private $validator;

    /** @var string */
    private $validationGroup;

    public function __construct(
        ObjectManager $administrationRoleManager,
        AdministrationRoleFactoryInterface $administrationRoleFactory,
        RepositoryInterface $administrationRoleRepository,
        AdministrationRoleValidatorInterface $validator,
        string $validationGroup
    ) {
        $this->administrationRoleManager = $administrationRoleManager;
        $this->administrationRoleFactory = $administrationRoleFactory;
        $this->administrationRoleRepository = $administrationRoleRepository;
        $this->validator = $validator;
        $this->validationGroup = $validationGroup;
    }

    public function __invoke(UpdateAdministrationRole $message): void
    {
        /** @var AdministrationRoleInterface|null $administrationRole */
        $administrationRole = $this
            ->administrationRoleRepository
            ->find($message->administrationRoleId())
        ;

        if (null === $administrationRole) {
            throw new \InvalidArgumentException('sylius_rbac.administration_role_does_not_exist');
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
