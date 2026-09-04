<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;

/**
 * One concrete permission, written `{package}.{subject}.{operation}` — for example
 * `sylius.product.update` or `odiseo_rbac.administration_role.create`.
 *
 * The format is Sylius' own: `Sylius\Component\Resource\Metadata::getPermissionCode()` builds
 * exactly this string, and `RequestConfiguration::getPermission()` hands it to the resource
 * controller's authorization checker. Adopting it verbatim is what makes 153 of the 167 admin
 * routes emit usable permission codes without anyone declaring anything.
 *
 * `package` is the bundle that owns the subject, never a business domain: "catalog"/"sales"
 * style domains were rejected because they mirror the admin menu, which is presentation and
 * moves. `subject` is usually a Sylius resource but may be a capability noun where no resource
 * exists, as in `sylius.impersonation.execute`.
 *
 * Always exactly three segments: `sylius.*` is not accepted, `sylius.*.*` is. Two spellings for
 * one concept would make patterns ambiguous to compare and to render as a tree.
 */
final readonly class PermissionIdentifier implements \Stringable
{
    public const SEPARATOR = '.';

    public const SEGMENT_COUNT = 3;

    private const SEGMENT_FORMAT = '/^[a-z][a-z0-9_]*$/';

    private function __construct(
        public string $package,
        public string $subject,
        public string $operation,
    ) {
    }

    public static function of(string $package, string $subject, string $operation): self
    {
        $value = implode(self::SEPARATOR, [$package, $subject, $operation]);

        foreach ([$package, $subject, $operation] as $segment) {
            self::assertValidSegment($segment, $value);
        }

        return new self($package, $subject, $operation);
    }

    public static function fromString(string $identifier): self
    {
        $segments = explode(self::SEPARATOR, $identifier);

        if (self::SEGMENT_COUNT !== count($segments)) {
            throw InvalidPermissionSyntaxException::wrongSegmentCount('identifier', $identifier, count($segments));
        }

        foreach ($segments as $segment) {
            self::assertValidSegment($segment, $identifier);
        }

        return new self($segments[0], $segments[1], $segments[2]);
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function toString(): string
    {
        return implode(self::SEPARATOR, [$this->package, $this->subject, $this->operation]);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Whether a string can stand as one segment of an identifier, asked without having to build
     * one and catch the failure. Used where a segment is derived from something outside the
     * plugin's control -- a URI, a workflow transition -- and a bad one means "not an
     * identifier" rather than "an error".
     */
    public static function isValidSegment(string $segment): bool
    {
        return PermissionPattern::WILDCARD !== $segment && 1 === preg_match(self::SEGMENT_FORMAT, $segment);
    }

    private static function assertValidSegment(string $segment, string $value): void
    {
        if (PermissionPattern::WILDCARD === $segment) {
            throw InvalidPermissionSyntaxException::unexpectedWildcard($value, $segment);
        }

        if (1 !== preg_match(self::SEGMENT_FORMAT, $segment)) {
            throw InvalidPermissionSyntaxException::malformedSegment('identifier', $value, $segment);
        }
    }
}
