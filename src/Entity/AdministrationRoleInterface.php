<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Sylius\Component\Resource\Model\ResourceInterface;
use Odiseo\SyliusRbacPlugin\Model\PermissionInterface;

interface AdministrationRoleInterface extends ResourceInterface
{
    public function getName(): ?string;

    public function setName(?string $name): void;

    public function addPermission(PermissionInterface $permission): void;

    public function removePermission(PermissionInterface $permission): void;

    public function clearPermissions(): void;

    public function hasPermission(PermissionInterface $permission): bool;

    public function getPermissions(): array;
}
