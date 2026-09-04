<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

/**
 * One row of the permission tree: everything that can be done to a single subject.
 */
final class PermissionSubject
{
    /** @var array<string, PermissionDefinition> operation => definition */
    private array $operations = [];

    public function __construct(
        public readonly string $key,
        public readonly string $package,
        public readonly string $name,
        /** What the administrator reads: the menu entry's own label, not the identifier. */
        public readonly string $label,
        /** The subject whose screen this one is reached from, when it has no screen of its own. */
        public readonly ?string $parent = null,
    ) {
    }

    public function add(PermissionDefinition $definition): void
    {
        $this->operations[$definition->identifier->operation] = $definition;
    }

    /** @return array<string, PermissionDefinition> */
    public function operations(): array
    {
        $operations = $this->operations;
        ksort($operations);

        return $operations;
    }

    public function has(string $operation): bool
    {
        return isset($this->operations[$operation]);
    }

    public function identifier(string $operation): string
    {
        return $this->key . '.' . $operation;
    }

    public function wildcard(): string
    {
        return $this->key . '.*';
    }
}
