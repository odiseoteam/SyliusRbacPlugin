<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Model;

final class AccessRequest
{
    public function __construct(
        private Section $section,
        private OperationType $operationType
    ) {
    }

    public function section(): Section
    {
        return $this->section;
    }

    public function operationType(): OperationType
    {
        return $this->operationType;
    }
}
