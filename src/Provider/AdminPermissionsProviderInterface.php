<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Provider;

interface AdminPermissionsProviderInterface
{
    public function getPossiblePermissions(): array;
}
