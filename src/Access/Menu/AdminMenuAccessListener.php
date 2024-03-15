<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Menu;

use Odiseo\SyliusRbacPlugin\Access\Checker\AdministratorAccessCheckerInterface;
use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;
use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Odiseo\SyliusRbacPlugin\Access\Model\Section;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Webmozart\Assert\Assert;

final class AdminMenuAccessListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AdministratorAccessCheckerInterface $accessChecker,
        private array $configuration,
    ) {
    }

    public function removeInaccessibleAdminMenuParts(MenuBuilderEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        Assert::notNull($token, 'There is no logged in user');

        $adminUser = $token->getUser();
        Assert::isInstanceOf($adminUser, AdminUserInterface::class, 'Logged in user should be an administrator');

        $menu = $event->getMenu();

        if ($this->hasAdminNoAccessToSection($adminUser, Section::catalog())) {
            $menu->removeChild('catalog');
        }

        if ($this->hasAdminNoAccessToSection($adminUser, Section::configuration())) {
            $menu->removeChild('configuration');
        }

        if ($this->hasAdminNoAccessToSection($adminUser, Section::customers())) {
            $menu->removeChild('customers');
        }

        if ($this->hasAdminNoAccessToSection($adminUser, Section::marketing())) {
            $menu->removeChild('marketing');
        }

        if ($this->hasAdminNoAccessToSection($adminUser, Section::sales())) {
            $menu->removeChild('sales');
        }

        /** @var string $customSection */
        foreach (array_keys($this->configuration['custom']) as $customSection) {
            if ($this->hasAdminNoAccessToSection($adminUser, Section::ofType($customSection))) {
                $menu->removeChild($customSection);
            }
        }
    }

    private function hasAdminNoAccessToSection(AdminUserInterface $adminUser, Section $section): bool
    {
        return !$this->accessChecker->canAccessSection(
            $adminUser,
            new AccessRequest($section, OperationType::read()),
        );
    }
}
