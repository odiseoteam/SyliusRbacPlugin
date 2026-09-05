<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

/**
 * One heading of the permission tree and the subjects filed under it.
 */
final class PermissionGroup
{
    /** @var array<string, PermissionSubject> */
    private array $subjects = [];

    /** @param array<string, int> $menuOrder subject key => position in the admin menu */
    public function __construct(
        public readonly string $name,
        private readonly array $menuOrder = [],
    ) {
    }

    public function add(PermissionDefinition $definition, string $label, ?string $parent = null): void
    {
        $key = $definition->identifier->package . '.' . $definition->identifier->subject;

        $this->subjects[$key] ??= new PermissionSubject(
            $key,
            $definition->identifier->package,
            $definition->identifier->subject,
            $label,
            $parent,
        );

        $this->subjects[$key]->add($definition);
    }

    /**
     * The rows in reading order: every subject with a screen of its own, in the order its entry
     * appears in the admin menu — falling back to alphabetical for one the menu never links to —
     * each followed by whatever is reached from inside it.
     *
     * @return list<PermissionSubject>
     */
    public function subjects(): array
    {
        $subjects = $this->subjects;
        uksort($subjects, fn (string $a, string $b): int => [$this->menuOrder[$a] ?? \PHP_INT_MAX, $a]
            <=> [$this->menuOrder[$b] ?? \PHP_INT_MAX, $b]);

        $children = [];

        foreach ($subjects as $key => $subject) {
            if (null !== $subject->parent && isset($subjects[$subject->parent])) {
                $children[$subject->parent][] = $subject;
                unset($subjects[$key]);
            }
        }

        $rows = [];

        foreach ($subjects as $key => $subject) {
            $rows[] = $subject;

            foreach ($children[$key] ?? [] as $child) {
                $rows[] = $child;
            }
        }

        return $rows;
    }

    /**
     * The columns this group's table needs: the shared operations its subjects use, and only
     * those.
     *
     * A column earns its width by saying something comparable about every row -- each resource
     * has the same six CRUD operations, so the column reads down the table and its "all" toggle
     * acts on more than one checkbox. Anything else belongs to one subject or two and renders
     * inside the row instead; see `PermissionSubject::extraOperations()`.
     *
     * Only the ones actually used: a section whose subjects are capabilities rather than
     * resources -- the dashboard is one -- would otherwise show six columns of dots to hold a
     * single "View" checkbox.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        $present = [];

        foreach ($this->subjects as $subject) {
            foreach (array_keys($subject->operations()) as $operation) {
                $present[$operation] = true;
            }
        }

        return array_values(array_filter(
            PermissionTree::COLUMNS,
            static fn (string $operation): bool => isset($present[$operation]),
        ));
    }

    /** Whether any row here has an operation of its own, and so needs the column that holds them. */
    public function hasExtraOperations(): bool
    {
        foreach ($this->subjects as $subject) {
            if ([] !== $subject->extraOperations()) {
                return true;
            }
        }

        return false;
    }

    public function permissionCount(): int
    {
        return array_sum(array_map(
            static fn (PermissionSubject $subject): int => count($subject->operations()),
            $this->subjects,
        ));
    }
}
