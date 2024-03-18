<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Message;

final class CreateAdministrationRole
{
    public function __construct(
        private string $administrationRoleName,
        private array $permissions = [],
    ) {
    }

    public function administrationRoleName(): string
    {
        return $this->administrationRoleName;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }
}
