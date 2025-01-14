<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

trait AdministrationRoleAwareTrait
{
    #[ORM\ManyToOne(targetEntity: AdministrationRoleInterface::class)]
    #[ORM\JoinColumn(name: 'administration_role_id', referencedColumnName: 'id')]
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
