<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Ui;

use Sylius\Behat\Page\Admin\Crud\CreatePageInterface;

interface AdministrationRoleCreatePageInterface extends CreatePageInterface
{
    public function nameIt(string $name): void;

    public function getNameValidationMessage(): string;
}
