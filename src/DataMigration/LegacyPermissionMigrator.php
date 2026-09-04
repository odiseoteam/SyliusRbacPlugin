<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;

/**
 * Turns what is stored in the pre-v3 format into v3 permission patterns.
 *
 * @internal
 */
final readonly class LegacyPermissionMigrator
{
    private const WILDCARD_SUFFIX = '.*';

    public function __construct(
        private LegacyRoleRepositoryInterface $repository,
        private LegacySectionPermissionTranslator $translator,
    ) {
    }

    /**
     * @return list<RoleMigration>
     */
    public function plan(): array
    {
        return array_map($this->planFor(...), $this->repository->findAll());
    }

    public function apply(RoleMigration $migration): void
    {
        $this->repository->updatePermissions($migration->role->id, $migration->patterns);
    }

    private function planFor(LegacyRole $role): RoleMigration
    {
        $patterns = [];
        $problems = $role->problems;

        foreach ($role->sections as $section => $writeAllowed) {
            if (!$this->translator->knowsSection($section)) {
                /**
                 * A section nobody configured route prefixes for. It was either a custom section
                 * whose configuration is already gone, or a leftover from a plugin that is no
                 * longer installed. Guessing would grant permissions nobody asked for, so the
                 * role is reported and left for a person to decide.
                 */
                $problems[] = sprintf('section "%s" is not configured, so nothing was granted for it', $section);

                continue;
            }

            $patterns = [...$patterns, ...$this->translator->translate($section, $writeAllowed)];
        }

        return new RoleMigration($role, $this->collapse($patterns), $problems);
    }

    /**
     * Drops exact identifiers already covered by a wildcard on the same subject, which happens
     * when one section grants write and another grants read over overlapping routes.
     *
     * @param list<string> $patterns
     *
     * @return list<string>
     */
    private function collapse(array $patterns): array
    {
        $wildcards = array_values(array_filter(
            $patterns,
            static fn (string $pattern): bool => str_ends_with($pattern, self::WILDCARD_SUFFIX),
        ));

        $kept = array_filter($patterns, static function (string $pattern) use ($wildcards): bool {
            if (str_ends_with($pattern, self::WILDCARD_SUFFIX)) {
                return true;
            }

            $identifier = PermissionIdentifier::fromString($pattern);
            $covering = sprintf('%s.%s%s', $identifier->package, $identifier->subject, self::WILDCARD_SUFFIX);

            return !in_array($covering, $wildcards, true);
        });

        $kept = array_values(array_unique($kept));
        sort($kept);

        return $kept;
    }
}
