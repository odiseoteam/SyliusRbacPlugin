<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;

/**
 * The admin menu with this plugin's own filtering held back.
 *
 * Whoever edits a role has to see every section, including the ones their own permissions hide;
 * a role editor that only offers what the editor can already reach cannot grant anything new.
 */
final readonly class UnfilteredMenuProvider implements UnfilteredMenuProviderInterface
{
    public function __construct(
        private MainMenuBuilder $menuBuilder,
        private MenuPermissionListener $filter,
    ) {
    }

    public function menu(): ?ItemInterface
    {
        $menu = $this->filter->suspended(fn (): ItemInterface => $this->menuBuilder->createMenu([]));

        return $menu instanceof ItemInterface ? $menu : null;
    }
}
