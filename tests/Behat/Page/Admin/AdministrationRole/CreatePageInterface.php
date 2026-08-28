<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\CreatePageInterface as BaseCreatePageInterface;

interface CreatePageInterface extends BaseCreatePageInterface
{
    /** @throws \Behat\Mink\Exception\ElementNotFoundException */
    public function fillName(string $name): void;
}
