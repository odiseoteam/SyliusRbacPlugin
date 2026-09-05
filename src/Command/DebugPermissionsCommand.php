<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Command;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\OrphanedRolePermissionFinder;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\OrphanedRouteFinder;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;
use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistry;
use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use Odiseo\SyliusRbacPlugin\Repository\AdministrationRoleRepositoryInterface;
use Odiseo\SyliusRbacPlugin\Security\EffectivePermissions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

/**
 * Lists the permission vocabulary, the way `debug:router` lists routes.
 *
 * The vocabulary is mostly discovered rather than written down, so without this the registry
 * could only be reviewed by reading the code that generates it.
 *
 * Unprotected routes are shown by default rather than hidden behind a flag: a route nothing
 * checks is the failure this plugin exists to prevent.
 */
#[AsCommand(
    name: 'odiseo:rbac:debug',
    description: 'Lists every permission the application knows about.',
)]
final class DebugPermissionsCommand extends Command
{
    /**
     * @param array<string, string> $declaredPermissions route name => permission identifier
     * @param list<string> $excludedRoutes
     */
    public function __construct(
        private readonly PermissionDiscovererInterface $discoverer,
        private readonly OrphanedRouteFinder $orphanedRouteFinder,
        private readonly OrphanedRolePermissionFinder $orphanedRolePermissionFinder,
        private readonly RoutePermissionMapInterface $routeMap,
        private readonly AdministrationRoleRepositoryInterface $administrationRoleRepository,
        private readonly RouterInterface $router,
        private readonly array $declaredPermissions = [],
        private readonly array $excludedRoutes = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('route', InputArgument::OPTIONAL, 'Show only this route: the permission it requires and which roles grant it')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Only show permissions whose "{package}.{subject}" starts with this')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with a failure if any admin route is left unchecked or any declaration is orphaned, so this can be run in CI')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $route */
        $route = $input->getArgument('route');

        if (null !== $route) {
            return $this->describeRoute($io, $route);
        }

        $discovered = $this->discoverer->discover();
        $registry = new PermissionRegistry($discovered->definitions);

        /** @var string|null $subjectFilter */
        $subjectFilter = $input->getOption('subject');
        $strict = true === $input->getOption('strict');

        $rows = [];

        foreach ($registry->all() as $identifier => $definition) {
            if (null !== $subjectFilter && !str_starts_with($identifier, $subjectFilter)) {
                continue;
            }

            $rows[] = [
                $identifier,
                $definition->group ?? '<fg=gray>—</>',
                $definition->label ?? '<fg=gray>—</>',
            ];
        }

        $io->title('RBAC permissions');

        if ([] === $rows) {
            $io->warning('No permission matches the given filters.');
        } else {
            $io->table(['Permission', 'Group', 'Label'], $rows);
        }

        $io->text(sprintf(
            '%d permissions, %d shown, from %d declarations.',
            count($registry->all()),
            count($rows),
            count($discovered->definitions),
        ));

        $this->reportUnprotectedRoutes($io, $discovered->unprotectedRoutes);
        $orphaned = $this->reportOrphanedDeclarations($io);
        $orphanedRolePermissions = $this->reportOrphanedRolePermissions($io);

        /**
         * Plain listing always succeeds: this is an inspection tool and scripts that pipe it
         * should not start failing. `--strict` is the opt-in for CI, and exists so a project can
         * guard itself against a route nobody protected, a declaration nobody cleaned up, or a
         * role still holding a permission that was renamed or dropped out from under it.
         */
        if ($strict && ([] !== $discovered->unprotectedRoutes || [] !== $orphaned || [] !== $orphanedRolePermissions)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /** @param array<string, string> $unprotectedRoutes */
    private function reportUnprotectedRoutes(SymfonyStyle $io, array $unprotectedRoutes): void
    {
        if ([] === $unprotectedRoutes) {
            $io->success('Every admin route is either covered by a permission or declared excluded.');

            return;
        }

        $io->section('Admin routes nothing checks');
        $io->table(
            ['Route', 'Why'],
            array_map(
                static fn (string $route, string $why): array => [$route, $why],
                array_keys($unprotectedRoutes),
                $unprotectedRoutes,
            ),
        );
        $io->warning(sprintf(
            '%d admin route(s) are reachable without any permission check. Give each one an entry under "odiseo_sylius_rbac.route_permissions", or list it under "excluded_routes" if leaving it open is the decision.',
            count($unprotectedRoutes),
        ));
    }

    /** @return list<string> every route name found orphaned, across both declarations */
    private function reportOrphanedDeclarations(SymfonyStyle $io): array
    {
        $rows = [
            ...array_map(
                static fn (string $route): array => [$route, 'route_permissions'],
                $this->orphanedRouteFinder->find($this->declaredPermissions),
            ),
            ...array_map(
                static fn (string $route): array => [$route, 'excluded_routes'],
                $this->orphanedRouteFinder->find($this->excludedRoutes),
            ),
        ];

        if ([] === $rows) {
            return [];
        }

        $io->section('Orphaned declarations');
        $io->table(['Route', 'Declared under'], $rows);
        $io->warning(sprintf(
            '%d declaration(s) point at a route that no longer exists. Sylius likely renamed it -- remove the stale entry.',
            count($rows),
        ));

        return array_column($rows, 0);
    }

    /** @return list<string> every role found holding an orphaned permission */
    private function reportOrphanedRolePermissions(SymfonyStyle $io): array
    {
        $stale = $this->orphanedRolePermissionFinder->find();

        if ([] === $stale) {
            return [];
        }

        $rows = [];

        foreach ($stale as $role => $patterns) {
            foreach ($patterns as $pattern) {
                $rows[] = [$role, $pattern];
            }
        }

        $io->section('Roles holding a permission that no longer exists');
        $io->table(['Role', 'Permission'], $rows);
        $io->warning(sprintf(
            '%d role(s) still hold a permission renamed or removed since it was granted. It grants nothing now -- edit the role to drop it.',
            count($stale),
        ));

        return array_keys($stale);
    }

    private function describeRoute(SymfonyStyle $io, string $route): int
    {
        $io->title(sprintf('Route "%s"', $route));

        if (null === $this->router->getRouteCollection()->get($route)) {
            $io->error(sprintf('Route "%s" does not exist.', $route));

            return Command::FAILURE;
        }

        if ($this->routeMap->isExcluded($route)) {
            $io->success('Open by design: listed under "excluded_routes", so every administrator can reach it.');

            return Command::SUCCESS;
        }

        $permission = $this->routeMap->permissionFor($route);

        if (null === $permission) {
            $io->warning('Nothing checks this route. Give it an entry under "odiseo_sylius_rbac.route_permissions", or list it under "excluded_routes" if leaving it open is the decision.');

            return Command::FAILURE;
        }

        try {
            $identifier = PermissionIdentifier::fromString($permission);
        } catch (InvalidPermissionSyntaxException $exception) {
            $io->error(sprintf('Requires "%s", which is not a well-formed permission: %s', $permission, $exception->getMessage()));

            return Command::FAILURE;
        }

        $io->text(sprintf('Requires: <info>%s</info>', $permission));

        $rows = [];

        foreach ($this->administrationRoleRepository->findAll() as $role) {
            /** @var AdministrationRoleInterface $role */
            $covers = EffectivePermissions::of($role->getPermissionPatterns())->allows($identifier);

            $rows[] = [$role->getCode(), $covers ? '<fg=green>yes</>' : '<fg=gray>no</>'];
        }

        if ([] === $rows) {
            $io->warning('No administration role exists yet, so nothing currently grants this.');

            return Command::SUCCESS;
        }

        $io->section('Roles');
        $io->table(['Role', 'Covers this route'], $rows);

        return Command::SUCCESS;
    }
}
