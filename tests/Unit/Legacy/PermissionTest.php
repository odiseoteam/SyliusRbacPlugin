<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Legacy;

use Odiseo\SyliusRbacPlugin\Legacy\OperationType;
use Odiseo\SyliusRbacPlugin\Legacy\Permission;
use PHPUnit\Framework\TestCase;

/**
 * The old format has to stay readable: it is what the data migration command (PR 6) will find
 * in the databases of everyone coming from 1.x / 2.x.
 */
final class PermissionTest extends TestCase
{
    public function testItRoundTripsTheSerializedFormatStoredInExistingDatabases(): void
    {
        $permission = Permission::ofType(
            Permission::CATALOG_MANAGEMENT_PERMISSION,
            [OperationType::read(), OperationType::write()],
        );

        $serialized = $permission->serialize();

        $this->assertJsonStringEqualsJsonString(
            '{"type":"catalog_management","operation_types":["read","write"]}',
            $serialized,
        );

        $unserialized = Permission::unserialize($serialized);

        $this->assertSame(Permission::CATALOG_MANAGEMENT_PERMISSION, $unserialized->type());
        $this->assertSame(
            ['read', 'write'],
            array_map(strval(...), $unserialized->operationTypes()),
        );
    }

    public function testItReadsAReadOnlyPermission(): void
    {
        $permission = Permission::unserialize('{"type":"sales_management","operation_types":["read"]}');

        $this->assertSame('sales_management', $permission->type());
        $this->assertCount(1, $permission->operationTypes());
        $this->assertSame(OperationType::READ, (string) $permission->operationTypes()[0]);
    }

    /**
     * Inherited trap, documented on purpose: `equals()` compares OperationTypes with
     * `in_array(..., strict: true)`, that is, by object identity. Two logically equal
     * permissions coming from separate deserializations compare as `false`.
     *
     * The data migration command (PR 6) must NOT rely on `equals()`: it has to compare
     * `type()` and the OperationTypes cast to string.
     */
    public function testEqualsComparesOperationTypesByIdentityAndCannotBeTrustedAcrossInstances(): void
    {
        $serialized = '{"type":"catalog_management","operation_types":["read","write"]}';

        $one = Permission::unserialize($serialized);
        $other = Permission::unserialize($serialized);

        $this->assertFalse($one->equals($other));
        $this->assertTrue($one->equals($one));
    }
}
