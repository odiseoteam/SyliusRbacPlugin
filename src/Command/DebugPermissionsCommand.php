<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Command;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    public function __construct(private readonly PermissionDiscovererInterface $discoverer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Only show permissions whose "{package}.{subject}" starts with this')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with a failure if any admin route is left unchecked, so this can be run in CI')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

        /**
         * Plain listing always succeeds: this is an inspection tool and scripts that pipe it
         * should not start failing. `--strict` is the opt-in for CI, and exists so a project can
         * guard itself against a route nobody protected.
         */
        if ($strict && [] !== $discovered->unprotectedRoutes) {
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
}
