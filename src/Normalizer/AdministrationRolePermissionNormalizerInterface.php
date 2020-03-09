<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Normalizer;

interface AdministrationRolePermissionNormalizerInterface
{
    public function normalize(array $administrationRolePermissions): array;
}
