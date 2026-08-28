<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\IndexPageInterface as BaseIndexPageInterface;

interface IndexPageInterface extends BaseIndexPageInterface
{
    public function deleteAdministrationRole(string $name): void;
}
