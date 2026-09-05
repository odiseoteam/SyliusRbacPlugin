<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistryInterface;
use Odiseo\SyliusRbacPlugin\Repository\AdministrationRoleRepositoryInterface;

/**
 * Which roles hold a permission pattern that no longer names anything real.
 *
 * A role stores the pattern, not its meaning -- see `PermissionPattern`. If the identifier it
 * spelled out gets renamed, or the plugin that declared it is uninstalled, the string survives
 * untouched: nothing in the save path checks it against the registry (`render()` in
 * `rbac-permissions.js` deduplicates and sorts the stored patterns, never filters them). The
 * role keeps the dead entry forever, granting nothing, until a human notices.
 *
 * Only exact patterns are checked. A wildcard such as `sylius.product.*` or `*.*.*` is never
 * orphaned: it is meant to keep matching subjects and operations added after the role was saved,
 * so matching nothing today is not a sign anything is wrong.
 */
final readonly class OrphanedRolePermissionFinder
{
    public function __construct(
        private PermissionRegistryInterface $registry,
        private AdministrationRoleRepositoryInterface $administrationRoleRepository,
    ) {
    }

    /** @return array<string, list<string>> role code => patterns naming nothing the registry knows */
    public function find(): array
    {
        $stale = [];

        foreach ($this->administrationRoleRepository->findAll() as $role) {
            /** @var AdministrationRoleInterface $role */
            $orphaned = [];

            foreach ($role->getPermissionPatterns() as $pattern) {
                if ($pattern->hasWildcard()) {
                    continue;
                }

                $identifier = PermissionIdentifier::of($pattern->package, $pattern->subject, $pattern->operation);

                if (!$this->registry->has($identifier)) {
                    $orphaned[] = $pattern->toString();
                }
            }

            if ([] !== $orphaned) {
                $stale[$role->getCode()] = $orphaned;
            }
        }

        return $stale;
    }
}
