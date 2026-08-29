<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

/**
 * What the migration intends to do to a single role, before it does it.
 *
 * Built and rendered before anything is written, because the only useful review of this
 * upgrade is a human reading the resulting grants next to the ones they replace.
 *
 * @internal
 */
final readonly class RoleMigration
{
    /**
     * @param list<string> $patterns permission patterns to store
     * @param list<string> $problems anything that could not be translated faithfully
     */
    public function __construct(
        public LegacyRole $role,
        public array $patterns = [],
        public array $problems = [],
    ) {
    }

    public function isSkipped(): bool
    {
        return $this->role->isAlreadyMigrated();
    }

    public function grantsNothing(): bool
    {
        return [] === $this->patterns;
    }
}
