<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Message;

final class UpdateAdministrationRole
{
    public function __construct(
        private int $administrationRoleId,
        private string $administrationRoleName,
        private array $permissions = []
    ) {
    }

    public function administrationRoleId(): int
    {
        return $this->administrationRoleId;
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
