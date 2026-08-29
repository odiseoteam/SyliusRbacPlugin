<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionPatternTest extends TestCase
{
    #[DataProvider('matchingCases')]
    public function testItMatchesSegmentBySegment(string $pattern, string $identifier, bool $expected): void
    {
        self::assertSame(
            $expected,
            PermissionPattern::fromString($pattern)->matches(PermissionIdentifier::fromString($identifier)),
        );
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function matchingCases(): iterable
    {
        yield 'exact' => ['sylius.product.update', 'sylius.product.update', true];
        yield 'exact, different operation' => ['sylius.product.update', 'sylius.product.delete', false];

        yield 'every operation on a subject' => ['sylius.product.*', 'sylius.product.bulk_delete', true];
        yield 'every operation stops at the subject' => ['sylius.product.*', 'sylius.order.index', false];

        yield 'whole package' => ['sylius.*.*', 'sylius.order.cancel', true];
        yield 'whole package excludes others' => ['sylius.*.*', 'odiseo_rbac.administration_role.index', false];

        // A read-only role across the whole admin is why wildcards are allowed in a leading position.
        yield 'read only across everything' => ['*.*.index', 'odiseo_rbac.administration_role.index', true];
        yield 'read only does not grant writes' => ['*.*.index', 'sylius.product.update', false];

        yield 'super admin' => ['*.*.*', 'sylius.impersonation.execute', true];

        // A wildcard matches a whole segment, never part of one.
        yield 'wildcard is not a substring match' => ['sylius.product.*', 'sylius.product_variant.update', false];
    }

    public function testAnyIsTheSuperAdministratorPattern(): void
    {
        $any = PermissionPattern::any();

        self::assertSame('*.*.*', $any->toString());
        self::assertTrue($any->matches(PermissionIdentifier::fromString('sylius.product.update')));
    }

    public function testAnIdentifierBecomesAPatternThatOnlyMatchesItself(): void
    {
        $identifier = PermissionIdentifier::fromString('sylius.product.update');
        $pattern = PermissionPattern::fromIdentifier($identifier);

        self::assertFalse($pattern->hasWildcard());
        self::assertTrue($pattern->matches($identifier));
        self::assertFalse($pattern->matches(PermissionIdentifier::fromString('sylius.product.delete')));
    }

    public function testItKnowsWhetherItCoversMoreThanOnePermission(): void
    {
        self::assertFalse(PermissionPattern::fromString('sylius.product.update')->hasWildcard());
        self::assertTrue(PermissionPattern::fromString('sylius.product.*')->hasWildcard());
        self::assertTrue(PermissionPattern::fromString('*.*.*')->hasWildcard());
    }

    public function testItComparesByValue(): void
    {
        $one = PermissionPattern::fromString('sylius.product.*');

        self::assertTrue($one->equals(PermissionPattern::fromString('sylius.product.*')));
        self::assertFalse($one->equals(PermissionPattern::fromString('sylius.order.*')));
    }

    /**
     * `sylius.*` and `sylius.*.*` would mean the same thing, and two spellings of one concept
     * make patterns ambiguous to compare and to render as a tree. Three segments, always.
     */
    #[DataProvider('malformedPatterns')]
    public function testItRefusesMalformedPatterns(string $pattern): void
    {
        $this->expectException(InvalidPermissionSyntaxException::class);

        PermissionPattern::fromString($pattern);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPatterns(): iterable
    {
        yield 'package shorthand' => ['sylius.*'];
        yield 'bare wildcard' => ['*'];
        yield 'four segments' => ['sylius.product.image.*'];
        yield 'empty segment' => ['sylius..*'];
        yield 'uppercase' => ['sylius.Product.*'];
        yield 'partial wildcard' => ['sylius.product_*.update'];
    }
}
