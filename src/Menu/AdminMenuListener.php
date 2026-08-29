<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    private const ADMINISTRATION_ITEM = 'sylius.ui.administration';

    private const CONFIGURATION_ITEM = 'configuration';

    private const ADMIN_USERS_ITEM = 'admin_users';

    private const ROLES_ITEM = 'roles';

    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $administration = $this->resolveAdministrationItem($menu);

        /**
         * Sylius ships this item as a placeholder pointing at an external product. It is dropped
         * rather than overwritten so both items below can be appended in the intended order, and
         * so the external URI, the `_blank` target and the badge that come with it cannot
         * survive.
         */
        $administration->removeChild(self::ROLES_ITEM);

        /**
         * "Administrators" lives under "Configuration" in a stock Sylius, next to channels,
         * currencies and tax rates — that section is store configuration, and access control is
         * not. Roles are assigned to administrators, so both belong together; grouping them here
         * keeps "Configuration" about the store and puts everything about who can do what in one
         * place.
         */
        $this->moveAdminUsersItem($menu, $administration);

        $administration
            ->addChild(self::ROLES_ITEM, ['route' => 'odiseo_rbac_admin_administration_role_index'])
            ->setLabel('sylius.ui.roles')
        ;
    }

    private function resolveAdministrationItem(ItemInterface $menu): ItemInterface
    {
        $administration = $menu->getChild(self::ADMINISTRATION_ITEM);

        if (null !== $administration) {
            return $administration;
        }

        return $menu
            ->addChild(self::ADMINISTRATION_ITEM)
            ->setLabel(self::ADMINISTRATION_ITEM)
            ->setLabelAttribute('icon', 'tabler:lock')
        ;
    }

    private function moveAdminUsersItem(ItemInterface $menu, ItemInterface $administration): void
    {
        $configuration = $menu->getChild(self::CONFIGURATION_ITEM);
        $adminUsers = $configuration?->getChild(self::ADMIN_USERS_ITEM);

        if (null === $configuration || null === $adminUsers) {
            return;
        }

        /** Detaching first is required: KnpMenu refuses to adopt an item that still has a parent. */
        $configuration->removeChild($adminUsers);

        $administration->addChild($adminUsers);
    }
}
