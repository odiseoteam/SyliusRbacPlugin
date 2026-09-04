<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface as BaseUpdatePageInterface;

interface UpdatePageInterface extends BaseUpdatePageInterface
{
    public function isCodeDisabled(): bool;

    public function fillName(string $name): void;

    /** @param list<string> $patterns */
    public function grantPermissions(array $patterns): void;

    public function getName(): string;
}
