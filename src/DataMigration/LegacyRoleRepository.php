<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

use Doctrine\DBAL\Connection;

/**
 * Reads and writes the role rows through DBAL rather than through the entity.
 *
 * `Entity\AdministrationRole` deliberately no longer exposes the old column, and going through
 * the ORM would also mean loading translations and firing lifecycle callbacks on a table this
 * command only needs a few columns of. DBAL keeps the migration independent of whatever shape
 * the entity takes later.
 *
 * @internal
 */
final readonly class LegacyRoleRepository implements LegacyRoleRepositoryInterface
{
    private const TABLE = 'odiseo_rbac_administration_role';

    public function __construct(
        private Connection $connection,
        private LegacyRoleFactory $factory,
    ) {
    }

    public function findAll(): array
    {
        $rows = $this->connection
            ->executeQuery(sprintf(
                'SELECT id, code, legacy_permissions, permissions FROM %s ORDER BY id',
                self::TABLE,
            ))
            ->fetchAllAssociative()
        ;

        return array_map($this->factory->fromRow(...), $rows);
    }

    public function updatePermissions(int $id, array $patterns): void
    {
        $this->connection->update(
            self::TABLE,
            ['permissions' => json_encode(array_values($patterns), \JSON_THROW_ON_ERROR)],
            ['id' => $id],
        );
    }
}
