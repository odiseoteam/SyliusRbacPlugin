<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\CreatePage as BaseCreatePage;

final class CreatePage extends BaseCreatePage implements CreatePageInterface
{
    public function fillName(string $name): void
    {
        $this->getDocument()->fillField('Name', $name);
    }

    /** @return array<string, string> */
    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'name' => '#odiseo_rbac_administration_role_name',
        ]);
    }
}
