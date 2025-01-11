<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $rbacSection = $menu
            ->addChild('rbac')
            ->setLabel('odiseo_sylius_rbac_plugin.ui.rbac')
            ->setLabelAttribute('icon', 'tabler:key')
        ;

        $rbacSection
            ->addChild('administration_roles', ['route' => 'odiseo_sylius_rbac_plugin_admin_administration_role_index'])
            ->setLabel('odiseo_sylius_rbac_plugin.ui.administration_roles')
        ;
    }
}
