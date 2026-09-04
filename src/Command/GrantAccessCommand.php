<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Command;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Odiseo\SyliusRbacPlugin\Security\EffectivePermissions;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gives an administrator a role, from outside the permission system.
 *
 * The way back when nobody can reach the roles screen any more: the role that granted it deleted,
 * a database edited by hand, an upgrade left half-applied. Nothing here consults the voter, which
 * is the point -- every other route to the same change is itself behind a permission.
 *
 * It can mint the role as well as assign it, because "all roles were deleted" is one of the
 * states it exists to repair, and assigning an existing role cannot fix that one.
 */
#[AsCommand(
    name: 'odiseo:rbac:grant',
    description: 'Gives an administrator an administration role, bypassing the permission check',
)]
final class GrantAccessCommand extends Command
{
    /** What has to be reachable for the roles screen to be usable at all. */
    private const ROLE_MANAGEMENT = [
        'odiseo_rbac.administration_role.index',
        'odiseo_rbac.administration_role.update',
    ];

    /**
     * @param RepositoryInterface<AdminUserInterface> $adminUserRepository
     * @param RepositoryInterface<AdministrationRoleInterface> $administrationRoleRepository
     * @param FactoryInterface<AdministrationRoleInterface> $administrationRoleFactory
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly RepositoryInterface $adminUserRepository,
        private readonly RepositoryInterface $administrationRoleRepository,
        private readonly FactoryInterface $administrationRoleFactory,
        private readonly RepositoryInterface $localeRepository,
        private readonly ObjectManager $objectManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('administrator', InputArgument::REQUIRED, 'Username or email of the administrator')
            ->addArgument('role', InputArgument::REQUIRED, 'Code of the administration role to grant')
            ->addOption('create', null, InputOption::VALUE_NONE, 'Create the role, granting everything, when no role has that code')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be written and exit without touching the database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        /** @var string $administratorName */
        $administratorName = $input->getArgument('administrator');
        /** @var string $roleCode */
        $roleCode = $input->getArgument('role');

        $administrator = $this->findAdministrator($administratorName);

        if (!$administrator instanceof AdministrationRoleAwareInterface) {
            $io->error(sprintf('No administrator found with username or email "%s".', $administratorName));

            return Command::FAILURE;
        }

        /**
         * Printed before anything changes, because the first question is whether the lockout is
         * real: if somebody else can still reach the roles screen, this command is the wrong tool.
         */
        $this->reportCurrentAccess($io);

        $role = $this->administrationRoleRepository->findOneBy(['code' => $roleCode]);
        $creating = !$role instanceof AdministrationRoleInterface;

        if ($creating && !$input->getOption('create')) {
            $io->error(sprintf('No administration role has the code "%s". Pass --create to make one granting everything.', $roleCode));

            return Command::FAILURE;
        }

        if ($role instanceof AdministrationRoleInterface && $administrator->hasAdministrationRole($role)) {
            $io->success(sprintf('"%s" already holds the role "%s". Nothing to do.', $administratorName, $roleCode));

            return Command::SUCCESS;
        }

        $io->section('Plan');
        $io->listing(array_filter([
            $creating ? sprintf('Create the role "%s", granting %s', $roleCode, PermissionPattern::any()->toString()) : null,
            sprintf('Give "%s" the role "%s"', $administratorName, $roleCode),
        ]));

        if ($dryRun) {
            $io->note('Nothing was written: --dry-run.');

            return Command::SUCCESS;
        }

        if (!$role instanceof AdministrationRoleInterface) {
            $role = $this->createRole($roleCode);
        }

        $administrator->addAdministrationRole($role);

        $this->objectManager->flush();

        $io->success(sprintf('"%s" now holds "%s".', $administratorName, $roleCode));

        return Command::SUCCESS;
    }

    /** Looked up by either, so whoever is locked out does not also have to guess which one it is. */
    private function findAdministrator(string $name): ?object
    {
        return $this->adminUserRepository->findOneBy(['username' => $name])
            ?? $this->adminUserRepository->findOneBy(['email' => $name]);
    }

    private function reportCurrentAccess(SymfonyStyle $io): void
    {
        $names = [];

        /** @var AdminUserInterface $administrator */
        foreach ($this->adminUserRepository->findAll() as $administrator) {
            if ($administrator instanceof AdministrationRoleAwareInterface && $this->managesRoles($administrator)) {
                $names[] = (string) $administrator->getUsername();
            }
        }

        if ([] === $names) {
            $io->warning('No administrator can currently manage roles.');

            return;
        }

        $io->section('Administrators who can currently manage roles');
        $io->listing($names);
    }

    private function managesRoles(AdministrationRoleAwareInterface $administrator): bool
    {
        $patterns = [];

        /** @var AdministrationRoleInterface $role */
        foreach ($administrator->getAdministrationRoles() as $role) {
            foreach ($role->getPermissionPatterns() as $pattern) {
                $patterns[$pattern->toString()] = $pattern;
            }
        }

        $effective = EffectivePermissions::of(array_values($patterns));

        foreach (self::ROLE_MANAGEMENT as $identifier) {
            if (!$effective->allows(PermissionIdentifier::fromString($identifier))) {
                return false;
            }
        }

        return true;
    }

    private function createRole(string $code): AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface $role */
        $role = $this->administrationRoleFactory->createNew();
        $role->setCode($code);
        $role->addPermissionPattern(PermissionPattern::any());

        /**
         * The name is translated, and a translatable entity has no current locale until it is
         * told: writing it without this fails with "No locale has been set".
         */
        foreach ($this->localeCodes() as $localeCode) {
            $role->setCurrentLocale($localeCode);
            $role->setFallbackLocale($localeCode);
            $role->setName(ucfirst(str_replace('_', ' ', $code)));
        }

        $this->objectManager->persist($role);

        return $role;
    }

    /** @return list<string> */
    private function localeCodes(): array
    {
        $codes = array_map(
            static fn (LocaleInterface $locale): string => (string) $locale->getCode(),
            $this->localeRepository->findAll(),
        );

        // A database with no locales yet still needs one to write the name under.
        return [] === $codes ? ['en_US'] : array_values($codes);
    }
}
