<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

/**
 * Version20260828120000 for PostgreSQL. Same steps, same order; the reasoning lives in the
 * MySQL file. Not left to `doctrine:schema:update` because it carries data: a schema diff sees
 * the rename of `permissions` as a drop and an add.
 */
final class Version20260828120001 extends AbstractPostgreSQLMigration
{
    use PendingLegacyPermissionsWarning;

    public function getDescription(): string
    {
        return 'Administration roles: stable code, translatable name, permission patterns, many roles per administrator';
    }

    public function postUp(Schema $schema): void
    {
        $this->warnAboutPendingLegacyPermissions('CAST(legacy_permissions AS TEXT)');
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role RENAME COLUMN permissions TO legacy_permissions');

        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ADD permissions JSON DEFAULT NULL');
        $this->addSql('UPDATE odiseo_rbac_administration_role SET permissions = \'[]\'');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ALTER COLUMN permissions SET NOT NULL');

        // The `g` flag matters: unlike MySQL, PostgreSQL replaces only the first match without it.
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ADD code VARCHAR(255) DEFAULT NULL');
        $this->addSql(
            'UPDATE odiseo_rbac_administration_role ' .
            "SET code = LOWER(REGEXP_REPLACE(name, '[^a-zA-Z0-9]+', '_', 'g')) || '_' || id",
        );
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ALTER COLUMN code SET NOT NULL');
        // Kept by PostgreSQL where MySQL drops it on MODIFY; left in place it shows up in
        // `doctrine:schema:validate`.
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ALTER COLUMN code DROP DEFAULT');
        $this->addSql('DROP INDEX UNIQ_BEFDB7615E237E06');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEFDB76177153098 ON odiseo_rbac_administration_role (code)');

        $this->addSql(
            'CREATE TABLE odiseo_rbac_administration_role_translation (' .
            'id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) NOT NULL, ' .
            'locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
        );
        $this->addSql(
            'CREATE INDEX IDX_4A87A0D02C2AC5D3 ' .
            'ON odiseo_rbac_administration_role_translation (translatable_id)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX odiseo_rbac_administration_role_translation_uniq_trans ' .
            'ON odiseo_rbac_administration_role_translation (translatable_id, locale)',
        );
        $this->addSql(
            'ALTER TABLE odiseo_rbac_administration_role_translation ' .
            'ADD CONSTRAINT FK_4A87A0D02C2AC5D3 FOREIGN KEY (translatable_id) ' .
            'REFERENCES odiseo_rbac_administration_role (id) ON DELETE CASCADE ' .
            'NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'INSERT INTO odiseo_rbac_administration_role_translation (translatable_id, name, locale) ' .
            "SELECT r.id, r.name, COALESCE((SELECT l.code FROM sylius_locale l ORDER BY l.id LIMIT 1), 'en_US') " .
            'FROM odiseo_rbac_administration_role r',
        );
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role DROP COLUMN name');

        $this->addSql(
            'CREATE TABLE odiseo_rbac_admin_user_administration_role (' .
            'admin_user_id INT NOT NULL, administration_role_id INT NOT NULL, ' .
            'PRIMARY KEY(admin_user_id, administration_role_id))',
        );
        $this->addSql(
            'CREATE INDEX IDX_221A2CED6352511C ' .
            'ON odiseo_rbac_admin_user_administration_role (admin_user_id)',
        );
        $this->addSql(
            'CREATE INDEX IDX_221A2CED913437BF ' .
            'ON odiseo_rbac_admin_user_administration_role (administration_role_id)',
        );
        $this->addSql(
            'ALTER TABLE odiseo_rbac_admin_user_administration_role ' .
            'ADD CONSTRAINT FK_221A2CED6352511C FOREIGN KEY (admin_user_id) ' .
            'REFERENCES sylius_admin_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE odiseo_rbac_admin_user_administration_role ' .
            'ADD CONSTRAINT FK_221A2CED913437BF FOREIGN KEY (administration_role_id) ' .
            'REFERENCES odiseo_rbac_administration_role (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'INSERT INTO odiseo_rbac_admin_user_administration_role (admin_user_id, administration_role_id) ' .
            'SELECT id, administration_role_id FROM sylius_admin_user WHERE administration_role_id IS NOT NULL',
        );

        $this->addSql('ALTER TABLE sylius_admin_user DROP CONSTRAINT FK_88D5CC4D913437BF');
        $this->addSql('DROP INDEX IDX_88D5CC4D913437BF');
        $this->addSql('ALTER TABLE sylius_admin_user DROP COLUMN administration_role_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_admin_user ADD administration_role_id INT DEFAULT NULL');
        $this->addSql(
            'UPDATE sylius_admin_user u SET administration_role_id = (' .
            'SELECT administration_role_id FROM odiseo_rbac_admin_user_administration_role j ' .
            'WHERE j.admin_user_id = u.id LIMIT 1)',
        );
        $this->addSql(
            'ALTER TABLE sylius_admin_user ADD CONSTRAINT FK_88D5CC4D913437BF ' .
            'FOREIGN KEY (administration_role_id) REFERENCES odiseo_rbac_administration_role (id) ' .
            'NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql('CREATE INDEX IDX_88D5CC4D913437BF ON sylius_admin_user (administration_role_id)');
        $this->addSql('DROP TABLE odiseo_rbac_admin_user_administration_role');

        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql(
            'UPDATE odiseo_rbac_administration_role r SET name = (' .
            'SELECT t.name FROM odiseo_rbac_administration_role_translation t ' .
            'WHERE t.translatable_id = r.id LIMIT 1)',
        );
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ALTER COLUMN name SET NOT NULL');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ALTER COLUMN name DROP DEFAULT');
        $this->addSql('DROP TABLE odiseo_rbac_administration_role_translation');

        $this->addSql('DROP INDEX UNIQ_BEFDB76177153098');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEFDB7615E237E06 ON odiseo_rbac_administration_role (name)');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role DROP COLUMN code');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role DROP COLUMN permissions');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role RENAME COLUMN legacy_permissions TO permissions');
    }
}
