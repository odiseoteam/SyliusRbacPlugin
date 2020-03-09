<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Checker;

use Sylius\Component\Core\Model\AdminUserInterface;
use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;

interface AdministratorAccessCheckerInterface
{
    public function canAccessSection(AdminUserInterface $admin, AccessRequest $accessRequest): bool;
}
