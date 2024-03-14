<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Cli;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class NormalizeExistingAdministratorsCommand extends Command
{
    public function __construct(
        private RepositoryInterface $administratorRepository,
        private RepositoryInterface $administratorRoleRepository,
        private ObjectManager $objectManager
    ) {
        parent::__construct('sylius-rbac:normalize-administrators');
    }

    protected function configure(): void
    {
        $this->setDescription('Assign no sections access role to all administrators in the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var AdministrationRoleInterface|null $noSectionsAccessRole */
        $noSectionsAccessRole = null;

        /** @var AdministrationRoleInterface $administrationRole */
        foreach ($this->administratorRoleRepository->findAll() as $administrationRole) {
            if (count($administrationRole->getPermissions()) === 0) {
                $noSectionsAccessRole = $administrationRole;
            }
        }

        if (null === $noSectionsAccessRole) {
            $output->writeln('There is no role with no access to any section. Aborting.');

            return 0;
        }

        $administrators = $this->administratorRepository->findAll();

        if (count($administrators) === 0) {
            $output->writeln('No administrators found. No migration needed.');

            return 0;
        }

        $output->writeln(sprintf('Found %d administrator(s). Migrating them.', count($administrators)));

        /** @var AdministrationRoleAwareInterface $administrator */
        foreach ($administrators as $administrator) {
            $administrator->setAdministrationRole($noSectionsAccessRole);
        }

        $this->objectManager->flush();

        return 0;
    }
}
