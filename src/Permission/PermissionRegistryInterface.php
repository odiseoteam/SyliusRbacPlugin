<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Exception\UnknownPermissionException;

/**
 * Everything the application can be asked permission for.
 *
 * The registry is the single source of truth for the permission vocabulary. It answers what
 * exists; it does not answer who may do what — that is `PermissionVoter`'s job.
 */
interface PermissionRegistryInterface
{
    /** @return array<string, PermissionDefinition> keyed and ordered by identifier */
    public function all(): array;

    public function has(PermissionIdentifier $identifier): bool;

    /** @throws UnknownPermissionException when nothing declared this permission */
    public function get(PermissionIdentifier $identifier): PermissionDefinition;

    /**
     * Every registered permission the pattern covers.
     *
     * Used to render what a wildcard actually grants, and to spot a stored pattern that no
     * longer matches anything — a role pointing at a resource some plugin removed.
     *
     * @return array<string, PermissionDefinition> keyed and ordered by identifier
     */
    public function matching(PermissionPattern $pattern): array;
}
