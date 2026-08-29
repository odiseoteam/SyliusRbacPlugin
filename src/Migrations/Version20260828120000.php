<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration;

/**
 * Moves administration roles to the v3 model.
 *
 * Schema only. The pre-v3 permission blob is carried over untouched into `legacy_permissions`
 * so `odiseo:rbac:migrate-permissions` can read and translate it; nothing here tries to
 * interpret it. Version20240315112759 is left alone — migrations are added, never edited.
 */
final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Administration roles: stable code, translatable name, permission patterns, many roles per administrator';
    }

    public function up(Schema $schema): void
    {
        // The old blob keeps its data under a name that says what it is. Dropped in 4.0.
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role CHANGE permissions legacy_permissions JSON NOT NULL');

        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ADD permissions JSON DEFAULT NULL');
        $this->addSql('UPDATE odiseo_rbac_administration_role SET permissions = \'[]\'');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role MODIFY permissions JSON NOT NULL');

        /**
         * Codes are derived from the name, which is what the roles were identified by until now.
         * The id is always appended rather than only on collision: `name` was unique but its
         * slug need not be — "Catalog Manager" and "Catalog-Manager" both reduce to
         * "catalog_manager" — and one deterministic statement beats a self-join that has to be
         * right the first time on someone else's production data. Codes are editable afterwards.
         */
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ADD code VARCHAR(255) DEFAULT NULL');
        $this->addSql(
            'UPDATE odiseo_rbac_administration_role ' .
            "SET code = CONCAT(LOWER(REGEXP_REPLACE(name, '[^a-zA-Z0-9]+', '_')), '_', id)",
        );
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role MODIFY code VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_BEFDB7615E237E06 ON odiseo_rbac_administration_role');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEFDB76177153098 ON odiseo_rbac_administration_role (code)');

        $this->addSql(
            'CREATE TABLE odiseo_rbac_administration_role_translation (' .
            'id INT AUTO_INCREMENT NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) NOT NULL, ' .
            'locale VARCHAR(255) NOT NULL, INDEX IDX_4A87A0D02C2AC5D3 (translatable_id), ' .
            'UNIQUE INDEX odiseo_rbac_administration_role_translation_uniq_trans (translatable_id, locale), ' .
            'PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB',
        );
        $this->addSql(
            'ALTER TABLE odiseo_rbac_administration_role_translation ' .
            'ADD CONSTRAINT FK_4A87A0D02C2AC5D3 FOREIGN KEY (translatable_id) ' .
            'REFERENCES odiseo_rbac_administration_role (id) ON DELETE CASCADE',
        );

        /**
         * Existing names become the translation for the shop's first locale, so they keep
         * rendering instead of landing under a locale nobody uses.
         *
         * The fallback matters: `sylius_locale` is empty on a fresh install, where migrations
         * run before fixtures. There are no roles to translate in that case either, so the
         * fallback normally never applies — but without it, any database that does have roles
         * and no locales yet fails here with a NOT NULL violation that says nothing about why.
         */
        $this->addSql(
            'INSERT INTO odiseo_rbac_administration_role_translation (translatable_id, name, locale) ' .
            "SELECT r.id, r.name, COALESCE((SELECT l.code FROM sylius_locale l ORDER BY l.id LIMIT 1), 'en_US') " .
            'FROM odiseo_rbac_administration_role r',
        );
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role DROP name');

        /**
         * One role per administrator forced shops to build a combinatorial role per person.
         * Existing assignments are carried into the join table before the column goes.
         */
        $this->addSql(
            'CREATE TABLE odiseo_rbac_admin_user_administration_role (' .
            'admin_user_id INT NOT NULL, administration_role_id INT NOT NULL, ' .
            'INDEX IDX_221A2CED6352511C (admin_user_id), INDEX IDX_221A2CED913437BF (administration_role_id), ' .
            'PRIMARY KEY(admin_user_id, administration_role_id)) ' .
            'DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB',
        );
        $this->addSql(
            'ALTER TABLE odiseo_rbac_admin_user_administration_role ' .
            'ADD CONSTRAINT FK_221A2CED6352511C FOREIGN KEY (admin_user_id) ' .
            'REFERENCES sylius_admin_user (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE odiseo_rbac_admin_user_administration_role ' .
            'ADD CONSTRAINT FK_221A2CED913437BF FOREIGN KEY (administration_role_id) ' .
            'REFERENCES odiseo_rbac_administration_role (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'INSERT INTO odiseo_rbac_admin_user_administration_role (admin_user_id, administration_role_id) ' .
            'SELECT id, administration_role_id FROM sylius_admin_user WHERE administration_role_id IS NOT NULL',
        );

        $this->addSql('ALTER TABLE sylius_admin_user DROP FOREIGN KEY FK_88D5CC4D913437BF');
        $this->addSql('DROP INDEX IDX_88D5CC4D913437BF ON sylius_admin_user');
        $this->addSql('ALTER TABLE sylius_admin_user DROP administration_role_id');
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
            'FOREIGN KEY (administration_role_id) REFERENCES odiseo_rbac_administration_role (id)',
        );
        $this->addSql('CREATE INDEX IDX_88D5CC4D913437BF ON sylius_admin_user (administration_role_id)');
        $this->addSql('DROP TABLE odiseo_rbac_admin_user_administration_role');

        $this->addSql('ALTER TABLE odiseo_rbac_administration_role ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql(
            'UPDATE odiseo_rbac_administration_role r SET name = (' .
            'SELECT t.name FROM odiseo_rbac_administration_role_translation t ' .
            'WHERE t.translatable_id = r.id LIMIT 1)',
        );
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role MODIFY name VARCHAR(255) NOT NULL');
        $this->addSql('DROP TABLE odiseo_rbac_administration_role_translation');

        $this->addSql('DROP INDEX UNIQ_BEFDB76177153098 ON odiseo_rbac_administration_role');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEFDB7615E237E06 ON odiseo_rbac_administration_role (name)');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role DROP code');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role DROP permissions');
        $this->addSql('ALTER TABLE odiseo_rbac_administration_role CHANGE legacy_permissions permissions JSON NOT NULL');
    }
}
