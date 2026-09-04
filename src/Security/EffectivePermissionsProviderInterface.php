<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;

interface EffectivePermissionsProviderInterface
{
    public function forAdministrator(AdministrationRoleAwareInterface $administrator): EffectivePermissions;
}
