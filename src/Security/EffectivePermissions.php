<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security;

use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;

/**
 * What one administrator can do, once all of their roles are taken together.
 *
 * Roles are additive and there is no deny rule: an administrator holding "catalog" and "orders"
 * can do the union of both. Negative permissions are deliberately absent — they turn every
 * question into "which rule wins?".
 */
final readonly class EffectivePermissions
{
    /** @param list<PermissionPattern> $patterns */
    private function __construct(public array $patterns)
    {
    }

    /** @param list<PermissionPattern> $patterns */
    public static function of(array $patterns): self
    {
        return new self(array_values($patterns));
    }

    /** An administrator with no roles at all. Denies everything, and says so without failing. */
    public static function none(): self
    {
        return new self([]);
    }

    public function allows(PermissionIdentifier $identifier): bool
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->matches($identifier)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return [] === $this->patterns;
    }

    /** @return list<string> */
    public function toStrings(): array
    {
        $strings = array_map(
            static fn (PermissionPattern $pattern): string => $pattern->toString(),
            $this->patterns,
        );

        sort($strings);

        return array_values(array_unique($strings));
    }
}
