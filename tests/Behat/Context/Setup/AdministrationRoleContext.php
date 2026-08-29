<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\Translation\Provider\TranslationLocaleProviderInterface;

final class AdministrationRoleContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private FactoryInterface $administrationRoleFactory,
        private RepositoryInterface $administrationRoleRepository,
        private TranslationLocaleProviderInterface $localeProvider,
        private ObjectManager $objectManager,
    ) {
    }

    /**
     * @Given my administrator account has full permissions
     *
     * An administrator holds no permissions until a role grants them, so every scenario that
     * opens an admin screen has to say which ones it has. Saying it here rather than hiding it
     * in a fixture keeps the features honest about what the plugin requires.
     */
    public function myAccountHasFullPermissions(): void
    {
        /** @var AdministrationRoleInterface $role */
        $role = $this->administrationRoleFactory->createNew();
        $role->setCode('behat_full_access');
        $role->addPermissionPattern(PermissionPattern::any());

        foreach ($this->localeProvider->getDefinedLocalesCodes() as $localeCode) {
            $role->setCurrentLocale($localeCode);
            $role->setFallbackLocale($localeCode);
            $role->setName('Full access');
        }

        $this->administrationRoleRepository->add($role);

        $administrator = $this->sharedStorage->get('administrator');

        if ($administrator instanceof AdministrationRoleAwareInterface) {
            $administrator->addAdministrationRole($role);

            $this->objectManager->flush();
        }
    }

    /**
     * @Given there is already an administration role :name in the system
     */
    public function thereIsAlreadyAnAdministrationRole(string $name): void
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->administrationRoleFactory->createNew();
        $administrationRole->setCode(strtolower(str_replace(' ', '_', $name)));

        /**
         * A translatable entity has no current locale until it is told, so writing the name
         * without this fails with "No locale has been set".
         */
        foreach ($this->localeProvider->getDefinedLocalesCodes() as $localeCode) {
            $administrationRole->setCurrentLocale($localeCode);
            $administrationRole->setFallbackLocale($localeCode);

            $administrationRole->setName($name);
        }

        $this->administrationRoleRepository->add($administrationRole);

        $this->sharedStorage->set('administration_role', $administrationRole);
    }
}
