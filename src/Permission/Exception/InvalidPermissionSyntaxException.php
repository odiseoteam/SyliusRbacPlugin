<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Exception;

/**
 * Thrown when a permission string is malformed.
 *
 * Malformed input is always an error, never a silent deny: a typo like `sylius.product.updte`
 * must fail loudly where it is written instead of quietly denying access forever, which is how
 * the pre-v3 engine hid its gaps.
 */
final class InvalidPermissionSyntaxException extends \InvalidArgumentException
{
    public static function wrongSegmentCount(string $subject, string $value, int $found): self
    {
        return new self(sprintf(
            'A permission %s must have exactly 3 dot-separated segments, "%s" has %d. Expected "{package}.{subject}.{operation}".',
            $subject,
            $value,
            $found,
        ));
    }

    public static function malformedSegment(string $subject, string $value, string $segment): self
    {
        return new self(sprintf(
            'Segment "%s" of permission %s "%s" is not valid. Segments must be lowercase and start with a letter, e.g. "product" or "bulk_delete".',
            $segment,
            $subject,
            $value,
        ));
    }

    public static function unexpectedWildcard(string $value, string $segment): self
    {
        return new self(sprintf(
            'Permission identifier "%s" must not contain wildcards, found "%s". Use %s for a pattern that matches several permissions.',
            $value,
            $segment,
            \Odiseo\SyliusRbacPlugin\Permission\PermissionPattern::class,
        ));
    }
}
