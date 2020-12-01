<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Message;

final class UpdateAdministrationRole
{
    /** @var int */
    private $administrationRoleId;

    /** @var string */
    private $administrationRoleName;

    /** @var array */
    private $permissions;

    public function __construct(int $administrationRoleId, string $administrationRoleName, array $permissions = [])
    {
        $this->administrationRoleId = $administrationRoleId;
        $this->administrationRoleName = $administrationRoleName;
        $this->permissions = $permissions;
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
