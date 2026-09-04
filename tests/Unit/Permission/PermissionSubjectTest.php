<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionSubject;
use PHPUnit\Framework\TestCase;

final class PermissionSubjectTest extends TestCase
{
    public function testItSpellsOutTheIdentifierAndTheWildcardOfItsRow(): void
    {
        $subject = $this->subject();

        self::assertSame('sylius.order.cancel', $subject->identifier('cancel'));
        self::assertSame('sylius.order.*', $subject->wildcard());
    }

    public function testItKnowsWhichOperationsItHas(): void
    {
        $subject = $this->subject('index', 'cancel');

        self::assertTrue($subject->has('cancel'));
        self::assertFalse($subject->has('delete'));
        self::assertSame(['cancel', 'index'], array_keys($subject->operations()));
    }

    public function testItIsNotNestedUnlessItIsReachedFromAnotherSubject(): void
    {
        self::assertNull($this->subject()->parent);
        self::assertSame('sylius.product', (new PermissionSubject(
            'sylius.product_taxon',
            'sylius',
            'product_taxon',
            'Taxa',
            'sylius.product',
        ))->parent);
    }

    private function subject(string ...$operations): PermissionSubject
    {
        $subject = new PermissionSubject('sylius.order', 'sylius', 'order', 'Orders');

        foreach ($operations as $operation) {
            $subject->add(new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.' . $operation)));
        }

        return $subject;
    }
}
