<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

/**
 * Version20240315112759 for PostgreSQL: the pre-v3 administration role table. Added with the v3
 * migrations because the original shipped for MySQL only, leaving Version20260828120001 with
 * nothing to transform. Numbered right after the migration it mirrors so it runs in the same
 * place in the chain.
 */
final class Version20240315112760 extends AbstractPostgreSQLMigration
{
    private const TABLE = 'odiseo_rbac_administration_role';

    public function getDescription(): string
    {
        return 'Pre-v3 administration role table (PostgreSQL)';
    }

    /**
     * Skipped where the table already exists: built by `doctrine:schema:update` back when no
     * migration shipped for this platform, and on the pre-v3 shape like any other.
     */
    public function preUp(Schema $schema): void
    {
        parent::preUp($schema);

        $this->skipIf($schema->hasTable(self::TABLE), sprintf('Table "%s" already exists.', self::TABLE));
    }

    public function preDown(Schema $schema): void
    {
        parent::preDown($schema);

        $this->skipIf(!$schema->hasTable(self::TABLE), sprintf('Table "%s" does not exist.', self::TABLE));
    }

    public function up(Schema $schema): void
    {
        // A sequence rather than SERIAL, as Sylius does: Doctrine's AUTO strategy expects one on
        // PostgreSQL, and the column default SERIAL leaves shows up in `doctrine:schema:validate`.
        $this->addSql('CREATE SEQUENCE odiseo_rbac_administration_role_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(
            'CREATE TABLE odiseo_rbac_administration_role (' .
            'id INT NOT NULL, name VARCHAR(255) NOT NULL, permissions JSON NOT NULL, ' .
            'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ' .
            'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))',
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEFDB7615E237E06 ON odiseo_rbac_administration_role (name)');
        $this->addSql('ALTER TABLE sylius_admin_user ADD administration_role_id INT DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE sylius_admin_user ADD CONSTRAINT FK_88D5CC4D913437BF ' .
            'FOREIGN KEY (administration_role_id) REFERENCES odiseo_rbac_administration_role (id) ' .
            'NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql('CREATE INDEX IDX_88D5CC4D913437BF ON sylius_admin_user (administration_role_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_admin_user DROP CONSTRAINT FK_88D5CC4D913437BF');
        $this->addSql('DROP INDEX IDX_88D5CC4D913437BF');
        $this->addSql('ALTER TABLE sylius_admin_user DROP COLUMN administration_role_id');
        $this->addSql('DROP TABLE odiseo_rbac_administration_role');
        $this->addSql('DROP SEQUENCE odiseo_rbac_administration_role_id_seq');
    }
}
