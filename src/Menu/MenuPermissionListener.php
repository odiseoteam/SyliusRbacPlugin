<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Menu;

use Knp\Menu\ItemInterface;
use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Removes the menu entries the administrator cannot reach.
 *
 * Driven entirely by each item's own route: the permission is looked up from the same map the
 * request will use, so a visible entry always leads somewhere that opens. The pre-v3 listener
 * matched hard-coded child names instead, which silently stopped filtering the moment Sylius
 * renamed an item or a plugin added one.
 *
 * A parent that ends up with no children and no destination of its own is removed too —
 * otherwise the administrator is left with headings that expand into nothing.
 */
final class MenuPermissionListener
{
    /**
     * Lets the permission tree build the menu unfiltered.
     *
     * The tree derives its groups from the admin menu, and the menu the current administrator
     * sees is by definition only the part they may reach — deriving groups from it would hide
     * whole sections from whoever is editing a role.
     */
    private bool $suspended = false;

    public function suspended(callable $build): mixed
    {
        $this->suspended = true;

        try {
            return $build();
        } finally {
            $this->suspended = false;
        }
    }

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly RoutePermissionMapInterface $routePermissions,
    ) {
    }

    public function filterAdminMenu(MenuBuilderEvent $event): void
    {
        if ($this->suspended) {
            return;
        }

        $this->filter($event->getMenu());
    }

    private function filter(ItemInterface $item): void
    {
        foreach ($item->getChildren() as $name => $child) {
            $this->filter($child);

            if ($this->shouldRemove($child)) {
                $item->removeChild($name);
            }
        }
    }

    private function shouldRemove(ItemInterface $item): bool
    {
        $uri = $item->getUri();
        $route = $this->routeOf($item);

        /**
         * The URI comes first, and the `routes` extra is only a fallback.
         *
         * That extra is not the item's destination: Sylius lists every route under which the
         * entry should be highlighted, so the "Products" entry carries the create and update
         * routes alongside the index one. Reading the first of them asked for
         * `sylius.product.create` to show a link that leads to the list.
         */
        $permission = null !== $uri
            ? $this->routePermissions->permissionForPath($uri)
            : (null !== $route ? $this->routePermissions->permissionFor($route) : null);

        if (null !== $permission) {
            return !$this->authorizationChecker->isGranted($permission);
        }

        if (null !== $route || null !== $uri) {
            return false;
        }

        // A heading with nothing left under it: no destination of its own, no surviving child.
        return [] === $item->getChildren();
    }

    private function routeOf(ItemInterface $item): ?string
    {
        $routes = $item->getExtra('routes');

        if (!is_array($routes)) {
            return null;
        }

        foreach ($routes as $route) {
            if (is_array($route) && is_string($route['route'] ?? null)) {
                return $route['route'];
            }

            if (is_string($route)) {
                return $route;
            }
        }

        return null;
    }
}
