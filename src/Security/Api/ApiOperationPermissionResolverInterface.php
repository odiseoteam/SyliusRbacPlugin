<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Api;

interface ApiOperationPermissionResolverInterface
{
    /**
     * @return string|null the permission identifier, or null when the operation cannot be mapped
     *                     to a Sylius resource
     */
    public function resolve(string $resourceClass, string $operationName): ?string;
}
