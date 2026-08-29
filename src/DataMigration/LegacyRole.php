<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

/**
 * One administration role as it stands in the database, mid-upgrade.
 *
 * @internal
 */
final readonly class LegacyRole
{
    /**
     * @param array<string, bool> $sections legacy section name => whether write was granted
     * @param list<string> $currentPatterns what the v3 `permissions` column already holds
     * @param list<string> $problems parts of the stored blob that could not be read
     */
    public function __construct(
        public int $id,
        public string $code,
        public array $sections = [],
        public array $currentPatterns = [],
        public array $problems = [],
    ) {
    }

    public function isAlreadyMigrated(): bool
    {
        return [] !== $this->currentPatterns;
    }
}
