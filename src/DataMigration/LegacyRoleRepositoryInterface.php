<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

/**
 * @internal
 */
interface LegacyRoleRepositoryInterface
{
    /**
     * @return list<LegacyRole>
     */
    public function findAll(): array;

    /**
     * @param list<string> $patterns
     */
    public function updatePermissions(int $id, array $patterns): void;
}
