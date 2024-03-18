<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

interface AdministrationRoleAwareInterface
{
    public function getAdministrationRole(): ?AdministrationRoleInterface;

    public function setAdministrationRole(?AdministrationRoleInterface $administrationRole): void;
}
