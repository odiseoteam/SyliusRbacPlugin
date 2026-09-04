<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

interface PermissionDiscovererInterface
{
    public function discover(): DiscoveredPermissions;
}
