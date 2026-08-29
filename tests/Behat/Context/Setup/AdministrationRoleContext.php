<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
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
    ) {
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
