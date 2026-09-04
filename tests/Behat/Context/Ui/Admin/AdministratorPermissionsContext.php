<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Ui\Admin;

use Behat\Mink\Element\NodeElement;
use Behat\MinkExtension\Context\RawMinkContext;
use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Webmozart\Assert\Assert;

final class AdministratorPermissionsContext extends RawMinkContext
{
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly FactoryInterface $administrationRoleFactory,
        private readonly RepositoryInterface $administrationRoleRepository,
        private readonly TranslationLocaleProviderInterface $localeProvider,
        private readonly ObjectManager $objectManager,
    ) {
    }

    /**
     * @Given my administrator account is allowed to :patterns
     */
    public function myAccountIsAllowedTo(string $patterns): void
    {
        /** @var AdministrationRoleInterface $role */
        $role = $this->administrationRoleFactory->createNew();
        $role->setCode('behat_scoped_' . substr(md5($patterns), 0, 8));

        foreach (array_map('trim', explode(',', $patterns)) as $pattern) {
            $role->addPermissionPattern(PermissionPattern::fromString($pattern));
        }

        foreach ($this->localeProvider->getDefinedLocalesCodes() as $localeCode) {
            $role->setCurrentLocale($localeCode);
            $role->setFallbackLocale($localeCode);
            $role->setName('Scoped access');
        }

        $this->administrationRoleRepository->add($role);

        $administrator = $this->sharedStorage->get('administrator');
        Assert::isInstanceOf($administrator, AdministrationRoleAwareInterface::class);

        $administrator->addAdministrationRole($role);
        $this->objectManager->flush();
    }

    /**
     * @When I try to open :path
     */
    public function iTryToOpen(string $path): void
    {
        $this->visitPath($path);
    }

    /**
     * @Then I should be denied access
     */
    public function iShouldBeDeniedAccess(): void
    {
        Assert::same($this->getSession()->getStatusCode(), 403);
    }

    /**
     * @Then I should see the page
     */
    public function iShouldSeeThePage(): void
    {
        Assert::same($this->getSession()->getStatusCode(), 200);
    }

    /**
     * @Then the menu should not lead to :path
     */
    public function theMenuShouldNotLeadTo(string $path): void
    {
        Assert::null($this->menuLinkTo($path), sprintf('The menu still leads to "%s".', $path));
    }

    /**
     * @Then the menu should lead to :path
     */
    public function theMenuShouldLeadTo(string $path): void
    {
        Assert::notNull($this->menuLinkTo($path), sprintf('The menu does not lead to "%s".', $path));
    }

    /**
     * Matched by destination rather than by label: the label is translated and the markup around
     * it changes with the theme, while the destination is exactly what the permission governs.
     */
    private function menuLinkTo(string $path): ?NodeElement
    {
        return $this->getSession()->getPage()->find('xpath', sprintf('//aside//a[@href="%s"]', $path));
    }
}
