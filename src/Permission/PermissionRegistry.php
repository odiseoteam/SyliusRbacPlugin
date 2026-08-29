<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\UnknownPermissionException;

/**
 * Immutable registry, built once from everything discovery and declaration produced.
 *
 * Repeated identifiers are merged rather than rejected: discovery finds that a permission
 * exists, and a declaration elsewhere says what to call it. Both describe the same permission,
 * and the identifier namespace belongs to the declaring package, so two unrelated packages
 * cannot collide by accident.
 */
final class PermissionRegistry implements PermissionRegistryInterface
{
    /** @var array<string, PermissionDefinition> */
    private array $definitions = [];

    /** @param iterable<PermissionDefinition> $definitions */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $key = $definition->identifier->toString();

            $this->definitions[$key] = isset($this->definitions[$key])
                ? $this->definitions[$key]->mergedWith($definition)
                : $definition;
        }

        ksort($this->definitions);
    }

    public function all(): array
    {
        return $this->definitions;
    }

    public function has(PermissionIdentifier $identifier): bool
    {
        return isset($this->definitions[$identifier->toString()]);
    }

    public function get(PermissionIdentifier $identifier): PermissionDefinition
    {
        return $this->definitions[$identifier->toString()] ?? throw new UnknownPermissionException($identifier);
    }

    public function matching(PermissionPattern $pattern): array
    {
        return array_filter(
            $this->definitions,
            static fn (PermissionDefinition $definition): bool => $pattern->matches($definition->identifier),
        );
    }
}
