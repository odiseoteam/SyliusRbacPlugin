<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DataMigration;

use Odiseo\SyliusRbacPlugin\DataMigration\LegacyRole;
use Odiseo\SyliusRbacPlugin\DataMigration\LegacyRoleRepositoryInterface;

final class InMemoryLegacyRoleRepository implements LegacyRoleRepositoryInterface
{
    /** @var array<int, list<string>> */
    public array $written = [];

    /**
     * @param list<LegacyRole> $roles
     */
    public function __construct(private readonly array $roles = [])
    {
    }

    public function findAll(): array
    {
        return $this->roles;
    }

    public function updatePermissions(int $id, array $patterns): void
    {
        $this->written[$id] = $patterns;
    }
}
