<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Provider;

use Odiseo\SyliusRbacPlugin\Model\Permission;

final class AdminPermissionsProvider implements AdminPermissionsProviderInterface
{
    public function __construct(
        private array $rbacConfiguration
    ) {
        $configuration = [];
        foreach ($rbacConfiguration['custom'] as $customSection => $_customRoutes) {
            $configuration[$customSection] = $configuration;
        }

        $rbacConfiguration = array_merge(
            array_keys($configuration),
            [
                Permission::CATALOG_MANAGEMENT_PERMISSION,
                Permission::CONFIGURATION_PERMISSION,
                Permission::CUSTOMERS_MANAGEMENT_PERMISSION,
                Permission::MARKETING_MANAGEMENT_PERMISSION,
                Permission::SALES_MANAGEMENT_PERMISSION,
            ]
        );

        sort($rbacConfiguration);

        $this->rbacConfiguration = $rbacConfiguration;
    }

    public function getPossiblePermissions(): array
    {
        return $this->rbacConfiguration;
    }
}
