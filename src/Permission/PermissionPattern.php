<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;

/**
 * What a role actually stores: a permission identifier where any segment may be `*`.
 *
 * `sylius.product.*` grants every operation on products, `*.*.index` is a read-only role across
 * the whole application, and `*.*.*` is a super administrator.
 *
 * Roles store the pattern, never its expansion. Expanding `sylius.product.*` into today's six
 * operations at save time would freeze the role: the next Sylius release that adds an operation
 * to products would leave every existing role without it.
 *
 * There are no deny patterns. Mixing allow and deny forces a precedence rule, and precedence
 * rules make "can this role do X?" impossible to answer by looking.
 */
final readonly class PermissionPattern implements \Stringable
{
    public const WILDCARD = '*';

    private const SEGMENT_FORMAT = '/^[a-z][a-z0-9_]*$/';

    private function __construct(
        public string $package,
        public string $subject,
        public string $operation,
    ) {
    }

    public static function fromString(string $pattern): self
    {
        $segments = explode(PermissionIdentifier::SEPARATOR, $pattern);

        if (PermissionIdentifier::SEGMENT_COUNT !== count($segments)) {
            throw InvalidPermissionSyntaxException::wrongSegmentCount('pattern', $pattern, count($segments));
        }

        foreach ($segments as $segment) {
            if (self::WILDCARD === $segment) {
                continue;
            }

            if (1 !== preg_match(self::SEGMENT_FORMAT, $segment)) {
                throw InvalidPermissionSyntaxException::malformedSegment('pattern', $pattern, $segment);
            }
        }

        return new self($segments[0], $segments[1], $segments[2]);
    }

    public static function fromIdentifier(PermissionIdentifier $identifier): self
    {
        return new self($identifier->package, $identifier->subject, $identifier->operation);
    }

    public static function any(): self
    {
        return new self(self::WILDCARD, self::WILDCARD, self::WILDCARD);
    }

    public function matches(PermissionIdentifier $identifier): bool
    {
        return
            self::segmentMatches($this->package, $identifier->package) &&
            self::segmentMatches($this->subject, $identifier->subject) &&
            self::segmentMatches($this->operation, $identifier->operation)
        ;
    }

    /** Whether this pattern can match more than the single identifier spelled out in it. */
    public function hasWildcard(): bool
    {
        return in_array(self::WILDCARD, [$this->package, $this->subject, $this->operation], true);
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function toString(): string
    {
        return implode(PermissionIdentifier::SEPARATOR, [$this->package, $this->subject, $this->operation]);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function segmentMatches(string $patternSegment, string $identifierSegment): bool
    {
        return self::WILDCARD === $patternSegment || $patternSegment === $identifierSegment;
    }
}
