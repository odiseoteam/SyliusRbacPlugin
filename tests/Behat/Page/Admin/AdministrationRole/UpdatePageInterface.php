<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface as BaseUpdatePageInterface;

interface UpdatePageInterface extends BaseUpdatePageInterface
{
    /** @throws \Behat\Mink\Exception\ElementNotFoundException */
    public function fillName(string $name): void;

    public function getName(): string;
}
