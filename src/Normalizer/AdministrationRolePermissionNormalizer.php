<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Normalizer;

use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;

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
                    true
                );

                $hasWriteOperationType = in_array(
                    OperationType::WRITE,
                    array_keys($administrationRolePermissions[$administrationRolePermission]),
                    true
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
