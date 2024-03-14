<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Checker;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;
use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Odiseo\SyliusRbacPlugin\Access\Model\Section;
use Odiseo\SyliusRbacPlugin\Model\Permission;
use Webmozart\Assert\Assert;

final class AdministratorAccessChecker implements AdministratorAccessCheckerInterface
{
    public function canAccessSection(AdminUserInterface $admin, AccessRequest $accessRequest): bool
    {
        if ($admin instanceof AdministrationRoleAwareInterface) {
            $administrationRole = $admin->getAdministrationRole();
            Assert::notNull($administrationRole);

            /** @var Permission $permission */
            foreach ($administrationRole->getPermissions() as $permission) {
                if ($this->getSectionForPermission($permission)->equals($accessRequest->section())) {
                    if (OperationType::READ === $accessRequest->operationType()->__toString()) {
                        return true;
                    }

                    return $this->canWriteAccess($permission);
                }
            }
        }

        return false;
    }

    private function getSectionForPermission(Permission $permission): Section
    {
        return match (true) {
            $permission->equals(Permission::configuration()) => Section::configuration(),
            $permission->equals(Permission::catalogManagement()) => Section::catalog(),
            $permission->equals(Permission::marketingManagement()) => Section::marketing(),
            $permission->equals(Permission::customerManagement()) => Section::customers(),
            $permission->equals(Permission::salesManagement()) => Section::sales(),
            default => Section::ofType($permission->type()),
        };
    }

    private function canWriteAccess(Permission $permission): bool
    {
        /** @var OperationType $operationType */
        foreach ($permission->operationTypes() as $operationType) {
            if (OperationType::WRITE === $operationType->__toString()) {
                return true;
            }
        }

        return false;
    }
}
