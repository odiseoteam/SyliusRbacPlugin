<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

use Sylius\Behat\Page\Admin\Crud\UpdatePage as BaseUpdatePage;
use Webmozart\Assert\Assert;

final class UpdatePage extends BaseUpdatePage implements UpdatePageInterface
{
    public function fillName(string $name): void
    {
        $this->getDocument()->fillField('Name', $name);
    }

    public function getName(): string
    {
        $field = $this->getDocument()->findField('Name');
        Assert::notNull($field, 'The name field could not be found on the page.');

        $value = $field->getValue();
        Assert::string($value);

        return $value;
    }

    /** @return array<string, string> */
    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'name' => '#odiseo_rbac_administration_role_name',
        ]);
    }
}
