<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;

final readonly class PermissionRegistryFactory
{
    public function __construct(private PermissionDiscovererInterface $discoverer)
    {
    }

    public function createRegistry(): PermissionRegistryInterface
    {
        return new PermissionRegistry($this->discoverer->discover()->definitions);
    }
}
