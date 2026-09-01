<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole;

/**
 * Both pages reach the name through the translations collection rather than a plain `name` input.
 * Only the create page fills the code: on update it is rendered disabled.
 */
trait AdministrationRoleFormTrait
{
    public function fillName(string $name): void
    {
        $this->getElement('name')->setValue($name);
    }

    /**
     * The permission tree is an editor for this one field. Writing it directly is what the tree
     * does on every click, and it is the only way to exercise the form without a browser.
     *
     * @param list<string> $patterns
     */
    public function grantPermissions(array $patterns): void
    {
        $this->getElement('permissions')->setValue(implode("\n", $patterns));
    }

    /** @return array<string, string> */
    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'code' => '#odiseo_rbac_administration_role_code',
            /**
             * One input per locale the shop has. The first is the default locale's, and matching
             * by shape rather than by a hard-coded locale keeps this working when the test
             * application's locales change.
             */
            'name' => '[id^="odiseo_rbac_administration_role_translations_"][id$="_name"]',
            'permissions' => '#odiseo_rbac_administration_role_permissions',
        ]);
    }
}
