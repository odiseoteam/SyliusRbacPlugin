<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

trait AdministrationRoleAwareTrait
{
    protected ?AdministrationRoleInterface $administrationRole = null;

    public function getAdministrationRole(): ?AdministrationRoleInterface
    {
        return $this->administrationRole;
    }

    public function setAdministrationRole(?AdministrationRoleInterface $administrationRole): void
    {
        $this->administrationRole = $administrationRole;
    }
}
