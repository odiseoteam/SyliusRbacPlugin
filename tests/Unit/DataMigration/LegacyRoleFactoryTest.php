<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DataMigration;

use Odiseo\SyliusRbacPlugin\DataMigration\LegacyRoleFactory;
use PHPUnit\Framework\TestCase;

final class LegacyRoleFactoryTest extends TestCase
{
    public function testItReadsSectionsAndWhetherWriteWasGranted(): void
    {
        $role = (new LegacyRoleFactory())->fromRow([
            'id' => '7',
            'code' => 'catalog_manager',
            'legacy_permissions' => self::blob([
                'catalog_management' => ['read', 'write'],
                'sales_management' => ['read'],
            ]),
            'permissions' => '[]',
        ]);

        self::assertSame(7, $role->id);
        self::assertSame('catalog_manager', $role->code);
        self::assertSame(['catalog_management' => true, 'sales_management' => false], $role->sections);
        self::assertSame([], $role->problems);
        self::assertFalse($role->isAlreadyMigrated());
    }

    public function testARoleThatAlreadyHoldsPatternsIsRecognised(): void
    {
        $role = (new LegacyRoleFactory())->fromRow([
            'id' => '1',
            'code' => 'administrator',
            'legacy_permissions' => null,
            'permissions' => '["sylius.product.*"]',
        ]);

        self::assertTrue($role->isAlreadyMigrated());
        self::assertSame(['sylius.product.*'], $role->currentPatterns);
    }

    /**
     * Fifteen versions of a plugin leave odd rows behind. Reporting them beats both crashing
     * the upgrade and silently dropping a grant somebody is relying on.
     */
    public function testUnreadableEntriesAreReportedRatherThanThrown(): void
    {
        $role = (new LegacyRoleFactory())->fromRow([
            'id' => '3',
            'code' => 'odd',
            'legacy_permissions' => '{"broken":"not-json","wrong_shape":"{\"foo\":1}","nested":["a"]}',
            'permissions' => null,
        ]);

        self::assertSame([], $role->sections);
        self::assertCount(3, $role->problems);
    }

    public function testAColumnThatIsNotJsonIsReportedOnce(): void
    {
        $role = (new LegacyRoleFactory())->fromRow([
            'id' => '4',
            'code' => 'corrupt',
            'legacy_permissions' => 'a:1:{i:0;s:3:"old";}',
            'permissions' => '[]',
        ]);

        self::assertSame([], $role->sections);
        self::assertCount(1, $role->problems);
        self::assertStringContainsString('not valid JSON', $role->problems[0]);
    }

    /**
     * @param array<string, list<string>> $sections
     */
    private static function blob(array $sections): string
    {
        $encoded = [];

        foreach ($sections as $type => $operations) {
            $encoded[$type] = json_encode(['type' => $type, 'operation_types' => $operations], \JSON_THROW_ON_ERROR);
        }

        return json_encode($encoded, \JSON_THROW_ON_ERROR);
    }
}
