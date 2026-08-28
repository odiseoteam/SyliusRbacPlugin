<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ElementNotFoundException;
use FriendsOfBehat\PageObjectExtension\Page\UnexpectedPageException;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Behat\Service\Resolver\CurrentPageResolverInterface;
use Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole\CreatePageInterface;
use Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole\IndexPageInterface;
use Tests\Odiseo\SyliusRbacPlugin\Behat\Page\Admin\AdministrationRole\UpdatePageInterface;
use Webmozart\Assert\Assert;

final class ManagingAdministrationRolesContext implements Context
{
    public function __construct(
        private CurrentPageResolverInterface $currentPageResolver,
        private IndexPageInterface $indexPage,
        private CreatePageInterface $createPage,
        private UpdatePageInterface $updatePage,
    ) {
    }

    /**
     * @When I browse administration roles
     *
     * @throws UnexpectedPageException
     */
    public function iBrowseAdministrationRoles(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I want to add a new administration role
     *
     * @throws UnexpectedPageException
     */
    public function iWantToAddANewAdministrationRole(): void
    {
        $this->createPage->open();
    }

    /**
     * @When /^I want to edit the ("[^"]+" administration role)$/
     *
     * @throws UnexpectedPageException
     */
    public function iWantToEditTheAdministrationRole(AdministrationRoleInterface $administrationRole): void
    {
        $this->updatePage->open(['id' => $administrationRole->getId()]);
    }

    /**
     * @When I name it :name
     * @When I rename it to :name
     *
     * @throws ElementNotFoundException
     */
    public function iNameIt(string $name): void
    {
        $this->resolveCurrentPage()->fillName($name);
    }

    /**
     * @When I do not specify its name
     */
    public function iDoNotSpecifyItsName(): void
    {
        // deliberately left blank
    }

    /**
     * @When I add it
     */
    public function iAddIt(): void
    {
        $this->createPage->create();
    }

    /**
     * @When I save my changes
     */
    public function iSaveMyChanges(): void
    {
        $this->updatePage->saveChanges();
    }

    /**
     * @When /^I delete the ("[^"]+" administration role)$/
     */
    public function iDeleteTheAdministrationRole(AdministrationRoleInterface $administrationRole): void
    {
        /** @var string $name */
        $name = $administrationRole->getName();

        $this->indexPage->open();
        $this->indexPage->deleteAdministrationRole($name);
    }

    /**
     * @Then there should be :count administration roles in the system
     */
    public function thereShouldBeAdministrationRolesInTheSystem(int $count): void
    {
        $this->indexPage->open();

        Assert::same($this->indexPage->countItems(), $count);
    }

    /**
     * @Then there should be an administration role named :name
     */
    public function thereShouldBeAnAdministrationRoleNamed(string $name): void
    {
        $this->indexPage->open();

        Assert::true($this->indexPage->isSingleResourceOnPage(['name' => $name]));
    }

    /**
     * @Then there should be no administration role named :name
     */
    public function thereShouldBeNoAdministrationRoleNamed(string $name): void
    {
        $this->indexPage->open();

        Assert::false($this->indexPage->isSingleResourceOnPage(['name' => $name]));
    }

    /**
     * @Then its name should be :name
     */
    public function itsNameShouldBe(string $name): void
    {
        Assert::same($this->updatePage->getName(), $name);
    }

    /**
     * @Then I should be notified that :element is required
     */
    public function iShouldBeNotifiedThatElementIsRequired(string $element): void
    {
        Assert::contains(
            $this->resolveCurrentPage()->getValidationMessage($element),
            'cannot be blank',
        );
    }

    /**
     * @Then I should be notified that this name is already taken
     */
    public function iShouldBeNotifiedThatThisNameIsAlreadyTaken(): void
    {
        Assert::contains(
            $this->resolveCurrentPage()->getValidationMessage('name'),
            'already taken',
        );
    }

    private function resolveCurrentPage(): CreatePageInterface|UpdatePageInterface
    {
        /** @var CreatePageInterface|UpdatePageInterface $page */
        $page = $this->currentPageResolver->getCurrentPageWithForm([
            $this->createPage,
            $this->updatePage,
        ]);

        return $page;
    }
}
