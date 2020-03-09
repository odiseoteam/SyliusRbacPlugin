<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Provider;

interface AdminPermissionsProviderInterface
{
    /** @return array|string[] */
    public function getPossiblePermissions(): array;
}
