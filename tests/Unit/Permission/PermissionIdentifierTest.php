<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionIdentifierTest extends TestCase
{
    public function testItParsesTheFormatSyliusAlreadyEmits(): void
    {
        $identifier = PermissionIdentifier::fromString('sylius.product.update');

        self::assertSame('sylius', $identifier->package);
        self::assertSame('product', $identifier->subject);
        self::assertSame('update', $identifier->operation);
        self::assertSame('sylius.product.update', $identifier->toString());
    }

    public function testItRoundTripsPluginOwnedIdentifiers(): void
    {
        $value = 'odiseo_rbac.administration_role.bulk_delete';

        self::assertSame($value, (string) PermissionIdentifier::fromString($value));
    }

    public function testItComparesByValueRatherThanIdentity(): void
    {
        $one = PermissionIdentifier::fromString('sylius.product.update');
        $other = PermissionIdentifier::of('sylius', 'product', 'update');

        self::assertNotSame($one, $other);
        self::assertTrue($one->equals($other));
        self::assertFalse($one->equals(PermissionIdentifier::fromString('sylius.product.delete')));
    }

    /**
     * The middle segment is not required to be a Doctrine resource: capabilities that no
     * resource covers still need a name.
     */
    public function testItAcceptsCapabilityNounsAsSubject(): void
    {
        self::assertSame(
            'sylius.impersonation.execute',
            PermissionIdentifier::of('sylius', 'impersonation', 'execute')->toString(),
        );
    }

    #[DataProvider('malformedIdentifiers')]
    public function testItRefusesMalformedInputLoudly(string $identifier): void
    {
        $this->expectException(InvalidPermissionSyntaxException::class);

        PermissionIdentifier::fromString($identifier);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedIdentifiers(): iterable
    {
        yield 'two segments' => ['sylius.product'];
        yield 'four segments' => ['sylius.product.image.update'];
        yield 'empty string' => [''];
        yield 'empty segment' => ['sylius..update'];
        yield 'uppercase' => ['Sylius.product.update'];
        yield 'leading digit' => ['sylius.2product.update'];
        yield 'dash instead of underscore' => ['sylius.product-variant.update'];
        yield 'whitespace' => ['sylius.product .update'];
    }

    /**
     * A wildcard is a pattern, not an identifier. Keeping the two types apart is what stops a
     * role's stored pattern from being mistaken for something the registry can hold.
     */
    #[DataProvider('wildcardIdentifiers')]
    public function testItRefusesWildcards(string $identifier): void
    {
        $this->expectException(InvalidPermissionSyntaxException::class);

        PermissionIdentifier::fromString($identifier);
    }

    /** @return iterable<string, array{string}> */
    public static function wildcardIdentifiers(): iterable
    {
        yield 'trailing' => ['sylius.product.*'];
        yield 'middle' => ['sylius.*.update'];
        yield 'everything' => ['*.*.*'];
    }
}
