<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Model;

use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;

interface PermissionInterface
{
    public function operationTypes(): ?array;

    public function addOperationType(OperationType $operationType): void;

    public function type(): string;

    public function serialize(): string;
}
