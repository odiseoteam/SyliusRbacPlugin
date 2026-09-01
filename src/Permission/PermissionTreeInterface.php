<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

interface PermissionTreeInterface
{
    /** @return list<PermissionGroup> */
    public function groups(): array;
}
