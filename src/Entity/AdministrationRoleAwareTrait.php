<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait AdministrationRoleAwareTrait
{
    /** @var Collection<array-key, AdministrationRoleInterface> */
    #[ORM\ManyToMany(targetEntity: AdministrationRoleInterface::class)]
    #[ORM\JoinTable(name: 'odiseo_rbac_admin_user_administration_role')]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'administration_role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected Collection $administrationRoles;

    public function getAdministrationRoles(): Collection
    {
        return $this->administrationRoles ??= new ArrayCollection();
    }

    public function addAdministrationRole(AdministrationRoleInterface $administrationRole): void
    {
        if (!$this->hasAdministrationRole($administrationRole)) {
            $this->getAdministrationRoles()->add($administrationRole);
        }
    }

    public function removeAdministrationRole(AdministrationRoleInterface $administrationRole): void
    {
        $this->getAdministrationRoles()->removeElement($administrationRole);
    }

    public function hasAdministrationRole(AdministrationRoleInterface $administrationRole): bool
    {
        return $this->getAdministrationRoles()->contains($administrationRole);
    }
}
