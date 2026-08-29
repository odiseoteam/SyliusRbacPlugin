<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;

interface AdministrationRoleInterface extends
    ResourceInterface,
    CodeAwareInterface,
    TranslatableInterface,
    TimestampableInterface
{
    public function getName(): ?string;

    public function setName(?string $name): void;

    /** @return list<PermissionPattern> */
    public function getPermissionPatterns(): array;

    public function addPermissionPattern(PermissionPattern $pattern): void;

    public function removePermissionPattern(PermissionPattern $pattern): void;

    public function hasPermissionPattern(PermissionPattern $pattern): bool;

    public function clearPermissionPatterns(): void;
}
