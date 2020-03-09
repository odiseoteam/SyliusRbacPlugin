<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Element;

interface AdministrationRolesElementInterface
{
    public function selectAdministrationRole(string $administrationRoleName): void;

    public function canRemoveAdministrationRole(): bool;
}
