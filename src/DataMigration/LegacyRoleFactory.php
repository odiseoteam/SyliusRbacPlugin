<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

use Odiseo\SyliusRbacPlugin\Legacy\OperationType;
use Odiseo\SyliusRbacPlugin\Legacy\Permission;

/**
 * Reads one stored role row into something the migration can reason about.
 *
 * Kept apart from the repository so the parsing, which is the part that meets data in whatever
 * state previous versions left it, can be tested without a database.
 *
 * @internal
 */
final readonly class LegacyRoleFactory
{
    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): LegacyRole
    {
        $sections = [];
        $problems = [];

        foreach ($this->decodeList($row['legacy_permissions'] ?? null, $problems) as $key => $serialized) {
            if (!is_string($serialized)) {
                $problems[] = sprintf('entry "%s" is not a serialized permission', (string) $key);

                continue;
            }

            $permission = $this->unserialize($serialized);

            if (null === $permission) {
                $problems[] = sprintf('entry "%s" is not in the pre-v3 permission format', (string) $key);

                continue;
            }

            $operations = array_map(strval(...), $permission->operationTypes());
            $sections[$permission->type()] = in_array(OperationType::WRITE, $operations, true);
        }

        return new LegacyRole(
            (int) self::scalar($row['id'] ?? null),
            (string) self::scalar($row['code'] ?? null),
            $sections,
            $this->decodePatterns($row['permissions'] ?? null),
            $problems,
        );
    }

    /**
     * Rejects anything that is not a pre-v3 permission rather than letting `Permission` fail.
     *
     * `Permission::unserialize()` assumes well-formed input and reports malformed input as a
     * PHP warning about an array offset, which tells whoever runs the upgrade nothing about
     * their data.
     */
    private function unserialize(string $serialized): ?Permission
    {
        try {
            $decoded = json_decode($serialized, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded) || !is_string($decoded['type'] ?? null) || !is_array($decoded['operation_types'] ?? null)) {
            return null;
        }

        try {
            return Permission::unserialize($serialized);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<string> $problems
     *
     * @return array<array-key, mixed>
     */
    private function decodeList(mixed $value, array &$problems): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        try {
            $decoded = json_decode((string) self::scalar($value), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $problems[] = sprintf('stored permissions are not valid JSON: %s', $exception->getMessage());

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function scalar(mixed $value): string|int|float|bool
    {
        return is_scalar($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private function decodePatterns(mixed $value): array
    {
        $ignored = [];
        $decoded = $this->decodeList($value, $ignored);

        return array_values(array_filter($decoded, is_string(...)));
    }
}
