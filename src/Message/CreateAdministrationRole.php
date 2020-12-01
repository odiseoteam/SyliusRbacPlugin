<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Message;

final class CreateAdministrationRole
{
    /** @var string */
    private $administrationRoleName;

    /** @var array */
    private $permissions;

    public function __construct(string $administrationRoleName, array $permissions = [])
    {
        $this->administrationRoleName = $administrationRoleName;
        $this->permissions = $permissions;
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
