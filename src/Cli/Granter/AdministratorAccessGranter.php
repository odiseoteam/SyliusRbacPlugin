<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Cli\Granter;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Factory\AdministrationRoleFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Model\Permission;

final class AdministratorAccessGranter implements AdministratorAccessGranterInterface
{
    /** @var RepositoryInterface */
    private $administratorRepository;

    /** @var RepositoryInterface */
    private $administratorRoleRepository;

    /** @var ObjectManager */
    private $objectManager;

    public function __construct(
        RepositoryInterface $administratorRepository,
        RepositoryInterface $administratorRoleRepository,
        ObjectManager $objectManager,
        AdministrationRoleFactoryInterface $administrationRoleFactory
    ) {
        $this->administratorRepository = $administratorRepository;
        $this->administratorRoleRepository = $administratorRoleRepository;
        $this->objectManager = $objectManager;
        $this->administrationRoleFactory = $administrationRoleFactory;
    }

    public function __invoke(string $email, string $roleName, array $sections): void
    {
        /** @var AdministrationRoleAwareInterface|null $admin */
        $admin = $this->administratorRepository->findOneBy(['email' => $email]);

        if (null === $admin) {
            throw new \InvalidArgumentException(
                sprintf('Administrator with email address %s does not exist. Aborting.', $email))
            ;
        }

        $administrationRole = $this->getOrCreateAdministrationRole($roleName);

        foreach ($sections as $section) {
            $administrationRole->addPermission(
                Permission::ofType(
                    $section,
                    [OperationType::read(), OperationType::write()])
            );
        }

        $this->administratorRoleRepository->add($administrationRole);
        $admin->setAdministrationRole($administrationRole);

        $this->objectManager->flush();
    }

    private function getOrCreateAdministrationRole(string $roleName): AdministrationRoleInterface
    {
        // This behaviour is debatable - either just override the selected role or throw an exception.
        /** @var AdministrationRoleInterface|null $administrationRole */
        $administrationRole = $this->administratorRoleRepository->findOneBy(['name' => $roleName]);

        if (null === $administrationRole) {
            $administrationRole = $this->administrationRoleFactory->createWithName($roleName);
        }

        return $administrationRole;
    }
}
