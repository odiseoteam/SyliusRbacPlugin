<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\CreatePageInterface as BaseCreatePageInterface;

interface CreatePageInterface extends BaseCreatePageInterface
{
    public function fillCode(string $code): void;

    public function fillName(string $name): void;

    /** @param list<string> $patterns */
    public function grantPermissions(array $patterns): void;
}
