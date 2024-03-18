<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Ui;

use Sylius\Behat\Page\Admin\Crud\CreatePage;

final class AdministrationRoleCreatePage extends CreatePage implements AdministrationRoleCreatePageInterface
{
    public function nameIt(string $name): void
    {
        $this->getDocument()->fillField('Name', $name);
    }

    public function getNameValidationMessage(): string
    {
        return $this
            ->getDocument()
            ->find('css', '#odiseo_sylius_rbac_plugin_administration_role_name ~ .sylius-validation-error')
            ->getText()
        ;
    }
}
