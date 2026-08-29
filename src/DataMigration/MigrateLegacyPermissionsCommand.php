<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rewrites pre-v3 section grants as v3 permission patterns.
 *
 * Prints the full result before writing anything: an administrator quietly gaining or losing
 * access does not throw, so the printed plan is the only review there is.
 */
#[AsCommand(
    name: 'odiseo:rbac:migrate-permissions',
    description: 'Rewrites pre-v3 administration role permissions in the v3 format',
)]
final class MigrateLegacyPermissionsCommand extends Command
{
    public function __construct(private readonly LegacyPermissionMigrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be written and exit without touching the database')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Also rewrite roles that already hold v3 permissions')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');
        $overwrite = true === $input->getOption('overwrite');

        $migrations = $this->migrator->plan();

        if ([] === $migrations) {
            $io->success('No administration roles to migrate.');

            return Command::SUCCESS;
        }

        // A role that translates to nothing and already holds nothing is left alone, so the
        // summary does not report work that did not happen.
        $pending = array_values(array_filter(
            $migrations,
            static fn (RoleMigration $migration): bool => ($overwrite || !$migration->isSkipped()) &&
                !($migration->grantsNothing() && [] === $migration->role->currentPatterns),
        ));

        $this->renderPlan($io, $migrations, $overwrite);

        $problems = $this->collectProblems($migrations);

        if ([] !== $problems) {
            $io->warning('Some roles could not be translated in full:');
            $io->listing($problems);
        }

        if ([] === $pending) {
            $io->success('Nothing left to write.');

            return [] === $problems ? Command::SUCCESS : Command::FAILURE;
        }

        if ($dryRun) {
            $io->note(sprintf('Dry run: %d role(s) would be rewritten. Nothing was written.', count($pending)));

            return [] === $problems ? Command::SUCCESS : Command::FAILURE;
        }

        /**
         * `--no-interaction` means "assume yes" here, as it does everywhere else in Symfony.
         * Asking `confirm()` in that mode would silently take the default and report success
         * without writing anything, which is the worst of both behaviours.
         */
        if (
            $input->isInteractive() &&
            !$io->confirm(sprintf('Write the permissions above to %d role(s)?', count($pending)), false)
        ) {
            $io->note('Nothing was written.');

            return Command::SUCCESS;
        }

        foreach ($pending as $migration) {
            $this->migrator->apply($migration);
        }

        $io->success(sprintf('Migrated %d administration role(s).', count($pending)));

        return [] === $problems ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<RoleMigration> $migrations
     */
    private function renderPlan(SymfonyStyle $io, array $migrations, bool $overwrite): void
    {
        $rows = [];

        foreach ($migrations as $migration) {
            $rows[] = [
                $migration->role->code,
                $this->describeSections($migration->role),
                $this->describeOutcome($migration, $overwrite),
            ];
        }

        $io->title('Administration role permissions');
        $io->table(['Role', 'Stored today', 'To be written'], $rows);
    }

    private function describeSections(LegacyRole $role): string
    {
        if ([] === $role->sections) {
            return '—';
        }

        $described = [];

        foreach ($role->sections as $section => $writeAllowed) {
            $described[] = sprintf('%s: %s', $section, $writeAllowed ? 'read + write' : 'read');
        }

        return implode("\n", $described);
    }

    private function describeOutcome(RoleMigration $migration, bool $overwrite): string
    {
        if ($migration->isSkipped() && !$overwrite) {
            return sprintf(
                '<comment>skipped, already migrated</comment>%s',
                "\n" . implode("\n", $migration->role->currentPatterns),
            );
        }

        if ($migration->grantsNothing()) {
            return '<comment>nothing — this role grants no access</comment>';
        }

        return implode("\n", $migration->patterns);
    }

    /**
     * @param list<RoleMigration> $migrations
     *
     * @return list<string>
     */
    private function collectProblems(array $migrations): array
    {
        $problems = [];

        foreach ($migrations as $migration) {
            foreach ($migration->problems as $problem) {
                $problems[] = sprintf('%s: %s', $migration->role->code, $problem);
            }
        }

        return $problems;
    }
}
