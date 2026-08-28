<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Legacy;

/**
 * Pre-v3 permission model (`Section` + `read`/`write`, serialized to JSON).
 *
 * Kept solely so the data migration command can read what is currently stored in user
 * databases. Do not use from new code: the data migration is the only layer allowed to
 * import `Legacy\` (see ROADMAP §5.1).
 *
 * @internal
 *
 * @deprecated removed in 4.0, once the data migration is no longer needed
 */
final class AdministrationRolePermissionNormalizer implements AdministrationRolePermissionNormalizerInterface
{
    public function normalize(?array $administrationRolePermissions): array
    {
        $normalizedPermissions = [];

        if (null !== $administrationRolePermissions) {
            foreach (array_keys($administrationRolePermissions) as $administrationRolePermission) {
                $hasReadOperationType = in_array(
                    OperationType::READ,
                    array_keys($administrationRolePermissions[$administrationRolePermission]),
                    true,
                );

                $hasWriteOperationType = in_array(
                    OperationType::WRITE,
                    array_keys($administrationRolePermissions[$administrationRolePermission]),
                    true,
                );

                if ($hasWriteOperationType) {
                    $normalizedPermissions[$administrationRolePermission][] = OperationType::read();
                    $normalizedPermissions[$administrationRolePermission][] = OperationType::write();

                    continue;
                }

                if ($hasReadOperationType) {
                    $normalizedPermissions[$administrationRolePermission][] = OperationType::read();
                }
            }
        }

        return $normalizedPermissions;
    }
}
