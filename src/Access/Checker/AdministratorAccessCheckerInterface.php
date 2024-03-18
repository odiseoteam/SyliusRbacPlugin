<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Checker;

use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;
use Sylius\Component\Core\Model\AdminUserInterface;

interface AdministratorAccessCheckerInterface
{
    public function canAccessSection(AdminUserInterface $admin, AccessRequest $accessRequest): bool;
}
