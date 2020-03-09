<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait AdministrationRoleAwareTrait
{
    /**
     * @ManyToOne(targetEntity="Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface")
     * @JoinColumn(name="administration_role_id", referencedColumnName="id")
     *
     * @var AdministrationRoleInterface|null
     */
    private $administrationRole;

    public function getAdministrationRole(): ?AdministrationRoleInterface
    {
        return $this->administrationRole;
    }

    public function setAdministrationRole(?AdministrationRoleInterface $administrationRole): void
    {
        $this->administrationRole = $administrationRole;
    }
}
