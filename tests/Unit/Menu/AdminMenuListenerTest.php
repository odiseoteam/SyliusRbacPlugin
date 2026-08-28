<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Menu;

use Knp\Menu\Factory\ExtensionInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Odiseo\SyliusRbacPlugin\Menu\AdminMenuListener;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListenerTest extends TestCase
{
    private MenuFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MenuFactory();

        /**
         * Stands in for Symfony's routing extension so `route` options end up in the URI. The
         * priority has to sit below KnpMenu's own CoreExtension (-10), which unconditionally
         * resets the URI to the `uri` option and would otherwise blank this out again.
         */
        $this->factory->addExtension(new class() implements ExtensionInterface {
            public function buildOptions(array $options): array
            {
                return $options;
            }

            public function buildItem(ItemInterface $item, array $options): void
            {
                if (isset($options['route'])) {
                    $item->setUri('/route/' . $options['route']);
                }
            }
        }, -20);
    }

    public function testItGroupsAdministratorsAndRolesUnderAdministration(): void
    {
        $menu = $this->createSyliusMenu();

        $this->dispatch($menu);

        $administration = $menu->getChild('sylius.ui.administration');
        self::assertNotNull($administration);
        self::assertSame(['admin_users', 'roles'], array_keys($administration->getChildren()));

        $configuration = $menu->getChild('configuration');
        self::assertNotNull($configuration);
        self::assertSame(['channels'], array_keys($configuration->getChildren()));
    }

    public function testItMovesTheAdministratorsItemWithoutBreakingItsRoute(): void
    {
        $menu = $this->createSyliusMenu();

        $this->dispatch($menu);

        $adminUsers = $menu->getChild('sylius.ui.administration')?->getChild('admin_users');

        self::assertNotNull($adminUsers);
        self::assertSame('/route/sylius_admin_admin_user_index', $adminUsers->getUri());
        self::assertSame('sylius.menu.admin.main.configuration.admin_users', $adminUsers->getLabel());
    }

    public function testItLeavesNoTraceOfTheSyliusPlusPlaceholder(): void
    {
        $menu = $this->createSyliusMenu();

        $this->dispatch($menu);

        $roles = $menu->getChild('sylius.ui.administration')?->getChild('roles');

        self::assertNotNull($roles);
        self::assertSame('/route/odiseo_rbac_admin_administration_role_index', $roles->getUri());
        self::assertSame('sylius.ui.roles', $roles->getLabel());
        self::assertNull($roles->getExtra('plus_logo'));
        self::assertNull($roles->getLinkAttribute('target'));
    }

    public function testItCreatesTheAdministrationSectionWhenSyliusCoreDoesNotProvideIt(): void
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('configuration')->addChild('admin_users', ['route' => 'sylius_admin_admin_user_index']);

        $this->dispatch($menu);

        $administration = $menu->getChild('sylius.ui.administration');

        self::assertNotNull($administration);
        self::assertSame('sylius.ui.administration', $administration->getLabel());
        self::assertSame('tabler:lock', $administration->getLabelAttribute('icon'));
        self::assertSame(['admin_users', 'roles'], array_keys($administration->getChildren()));
    }

    public function testItStillExposesRolesWhenTheAdministratorsItemIsAbsent(): void
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('sylius.ui.administration');

        $this->dispatch($menu);

        $administration = $menu->getChild('sylius.ui.administration');

        self::assertNotNull($administration);
        self::assertSame(['roles'], array_keys($administration->getChildren()));
    }

    private function dispatch(ItemInterface $menu): void
    {
        (new AdminMenuListener())->addAdminMenuItems(new MenuBuilderEvent($this->factory, $menu));
    }

    /** Mirrors the relevant slice of Sylius' own MainMenuBuilder. */
    private function createSyliusMenu(): ItemInterface
    {
        $menu = $this->factory->createItem('root');

        $configuration = $menu->addChild('configuration');
        $configuration->addChild('channels', ['route' => 'sylius_admin_channel_index']);
        $configuration
            ->addChild('admin_users', ['route' => 'sylius_admin_admin_user_index'])
            ->setLabel('sylius.menu.admin.main.configuration.admin_users')
        ;

        $menu
            ->addChild('sylius.ui.administration')
            ->addChild('roles')
            ->setUri('https://sylius.com/plus/?utm_campaign=rbac-placeholder')
            ->setLinkAttribute('target', '_blank')
            ->setLabel('sylius.ui.roles')
            ->setExtra('plus_logo', true)
        ;

        return $menu;
    }
}
