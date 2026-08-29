<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Menu;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Odiseo\SyliusRbacPlugin\Menu\MenuPermissionListener;
use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuPermissionListenerTest extends TestCase
{
    private const PERMISSIONS = [
        'sylius_admin_product_index' => 'sylius.product.index',
        'sylius_admin_order_index' => 'sylius.order.index',
        'sylius_admin_taxon_index' => 'sylius.taxon.index',
    ];

    public function testItRemovesEntriesTheAdministratorCannotReach(): void
    {
        $menu = $this->filter(['sylius.product.index']);

        self::assertSame(['catalog', 'support'], array_keys($menu->getChildren()));
        self::assertSame(['products'], array_keys($menu->getChild('catalog')->getChildren()));
    }

    /** A heading that expands into nothing is worse than no heading. */
    public function testItRemovesASectionWhoseEveryChildWasRemoved(): void
    {
        $menu = $this->filter(['sylius.product.index']);

        self::assertNull($menu->getChild('sales'));
    }

    public function testItKeepsEverythingForAnAdministratorWhoHoldsEverything(): void
    {
        $menu = $this->filter(['sylius.product.index', 'sylius.order.index', 'sylius.taxon.index']);

        self::assertSame(['catalog', 'sales', 'support'], array_keys($menu->getChildren()));
        self::assertCount(2, $menu->getChild('catalog')->getChildren());
    }

    /**
     * Sylius builds some entries with `setUri()` instead of a route — its own dashboard link is
     * one — so the path has to be matched back to a route. Reading the item's destination is
     * what keeps this from becoming a list of child names to look for, which is how the pre-v3
     * listener stopped filtering whenever anything was renamed.
     */
    public function testItFiltersAnEntryThatOnlyCarriesAUri(): void
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');
        $menu->addChild('dashboard')->setUri('/admin/');

        $this->filterMenu($menu, [], ['/admin/' => 'sylius.dashboard.view']);

        self::assertNull($menu->getChild('dashboard'));
    }

    /** An external link — documentation, support — has no permission and must survive. */
    public function testItKeepsAnEntryPointingOutsideTheApplication(): void
    {
        $menu = $this->filter([]);

        self::assertNotNull($menu->getChild('support'));
    }

    /**
     * @param list<string> $granted
     */
    private function filter(array $granted): ItemInterface
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');

        $catalog = $menu->addChild('catalog');
        $catalog->addChild('products')->setExtra('routes', [['route' => 'sylius_admin_product_index']]);
        $catalog->addChild('taxons')->setExtra('routes', [['route' => 'sylius_admin_taxon_index']]);

        $sales = $menu->addChild('sales');
        $sales->addChild('orders')->setExtra('routes', [['route' => 'sylius_admin_order_index']]);

        $menu->addChild('support')->setUri('https://example.com/docs');

        $this->filterMenu($menu, $granted);

        return $menu;
    }

    /**
     * @param list<string> $granted
     * @param array<string, string> $pathPermissions
     */
    private function filterMenu(ItemInterface $menu, array $granted, array $pathPermissions = []): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => in_array($attribute, $granted, true),
        );

        $map = $this->createMock(RoutePermissionMapInterface::class);
        $map->method('permissionFor')->willReturnCallback(
            static fn (string $route): ?string => self::PERMISSIONS[$route] ?? null,
        );
        $map->method('permissionForPath')->willReturnCallback(
            static fn (string $path): ?string => $pathPermissions[$path] ?? null,
        );

        (new MenuPermissionListener($checker, $map))->filterAdminMenu(new MenuBuilderEvent(new MenuFactory(), $menu));
    }
}
