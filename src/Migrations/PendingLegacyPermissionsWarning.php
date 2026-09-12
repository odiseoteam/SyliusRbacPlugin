<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Migrations;

/**
 * The warning both v3 schema migrations end on, shared so the MySQL and PostgreSQL ones cannot
 * drift apart.
 */
trait PendingLegacyPermissionsWarning
{
    /**
     * The schema alone leaves every role granting nothing until `odiseo:rbac:migrate-permissions`
     * runs, so every administrator is denied everything in between — including the screen that
     * would fix it. Said here because nobody reads the upgrade notes mid-deploy.
     *
     * @param string $blob the `legacy_permissions` column as text; PostgreSQL's `json` type has
     *        no equality operator, so it has to be cast there
     */
    private function warnAboutPendingLegacyPermissions(string $blob = 'legacy_permissions'): void
    {
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM odiseo_rbac_administration_role WHERE $blob NOT IN ('[]', '{}', '')",
        );

        $pending = is_numeric($count) ? (int) $count : 0;

        if (0 === $pending) {
            return;
        }

        $message = sprintf(
            '%d administration role(s) still hold pre-v3 permissions and currently grant nothing. ' .
            'Run "odiseo:rbac:migrate-permissions --dry-run" to review the translation, then the ' .
            'same command without --dry-run to apply it, before anyone signs in to the admin.',
            $pending,
        );

        $this->warnIf(true, $message);

        // `warnIf()` only reaches a logger, which prod usually does not wire to the console.
        if (\PHP_SAPI === 'cli' && defined('STDERR')) {
            fwrite(\STDERR, \PHP_EOL . '[WARNING] ' . $message . \PHP_EOL . \PHP_EOL);
        }
    }
}
