<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Odiseo\SyliusRbacPlugin\Menu\UnfilteredMenuProviderInterface;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionGroup;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistryInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionTree;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Metadata\MetadataInterface;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class PermissionTreeTest extends TestCase
{
    /**
     * The menu entries the tests build on: path => the resource controller behind it. The tree
     * asks the router what each menu entry leads to, so a subject is "on the menu" only when its
     * screen is actually linked.
     */
    private const SCREENS = [
        '/admin/products/' => 'sylius.controller.product::indexAction',
        '/admin/orders/' => 'sylius.controller.order::indexAction',
        '/admin/promotions/' => 'sylius.controller.promotion::indexAction',
        '/admin/customers/' => 'sylius.controller.customer::indexAction',
    ];

    /** @var array<string, string> */
    private array $pluralNames = [];

    public function testItFilesASubjectUnderTheMenuSectionItsScreenIsIn(): void
    {
        $tree = $this->tree(['sylius.product.index', 'sylius.order.index'], [
            'catalog' => ['Products' => '/admin/products/'],
            'sales' => ['Orders' => '/admin/orders/'],
        ]);

        self::assertSame(['catalog', 'sales'], $this->groupNames($tree));
        self::assertSame(['Products'], $this->labels($tree, 'catalog'));
    }

    /** The word the administrator already reads in the navigation, not the resource's own name. */
    public function testItLabelsARowWithItsMenuEntry(): void
    {
        $tree = $this->tree(['sylius.promotion.index'], ['marketing' => ['Cart promotions' => '/admin/promotions/']]);

        self::assertSame(['Cart promotions'], $this->labels($tree, 'marketing'));
    }

    public function testItFallsBackToTheResourcePluralNameWhenNothingLinksToTheSubject(): void
    {
        $this->pluralNames = ['sylius.payment_request' => 'payment_requests'];

        $tree = $this->tree(['sylius.payment_request.index'], []);

        self::assertSame(['Payment requests'], $this->labels($tree, 'not_on_the_menu'));
    }

    /** For a permission whose middle segment names a capability with no resource behind it. */
    public function testItFallsBackToTheSubjectItselfWhenNoResourceDeclaresIt(): void
    {
        $tree = $this->tree(['sylius.live_component.execute'], []);

        self::assertSame(['Live component'], $this->labels($tree, 'not_on_the_menu'));
    }

    /**
     * Operations of one subject can disagree: coupon CRUD is reached from inside the promotion
     * screen, while `promotion_coupon.generate` is declared under marketing. Deciding per
     * permission split that subject across two sections.
     */
    public function testItDecidesTheSectionOncePerSubjectRatherThanPerPermission(): void
    {
        $tree = $this->tree(
            ['sylius.promotion_coupon.index', 'sylius.promotion_coupon.generate' => 'marketing'],
            ['marketing' => ['Cart promotions' => '/admin/promotions/']],
        );

        $subjects = $this->group($tree, 'marketing')->subjects();

        self::assertSame(['marketing'], $this->groupNames($tree));
        self::assertCount(1, $subjects);
        self::assertSame(['generate', 'index'], array_keys($subjects[0]->operations()));
    }

    public function testItNestsASubjectWhoseIdentifierExtendsAnotherOne(): void
    {
        $tree = $this->tree(
            ['sylius.promotion.index', 'sylius.promotion_coupon.index'],
            ['marketing' => ['Cart promotions' => '/admin/promotions/']],
        );

        $subjects = $this->group($tree, 'marketing')->subjects();

        self::assertSame(['sylius.promotion', 'sylius.promotion_coupon'], array_map(
            static fn ($subject): string => $subject->key,
            $subjects,
        ));
        self::assertSame('sylius.promotion', $subjects[1]->parent);
    }

    /** The row already sits under its parent, so repeating its name says the same word twice. */
    public function testItDropsTheParentsNameFromANestedLabel(): void
    {
        $this->pluralNames = ['sylius.promotion_coupon' => 'promotion_coupons'];

        $tree = $this->tree(
            ['sylius.promotion.index', 'sylius.promotion_coupon.index'],
            ['marketing' => ['Cart promotions' => '/admin/promotions/']],
        );

        self::assertSame(['Cart promotions', 'Coupons'], $this->labels($tree, 'marketing'));
    }

    public function testItFilesANestedSubjectWhereverItsParentIs(): void
    {
        $tree = $this->tree(
            ['sylius.promotion.index', 'sylius.promotion_coupon.index'],
            ['marketing' => ['Cart promotions' => '/admin/promotions/']],
        );

        self::assertSame(['marketing'], $this->groupNames($tree));
    }

    /** A subject with an entry of its own is a screen in its own right, whatever its name is. */
    public function testItNeverNestsASubjectThatIsOnTheMenu(): void
    {
        $tree = $this->tree(['sylius.product.index', 'sylius.order.index'], [
            'catalog' => ['Products' => '/admin/products/'],
            'sales' => ['Orders' => '/admin/orders/'],
        ]);

        foreach ($tree->groups() as $group) {
            foreach ($group->subjects() as $subject) {
                self::assertNull($subject->parent);
            }
        }
    }

    public function testItNestsUnderTheLongestMatchingSubject(): void
    {
        $tree = $this->tree(
            ['sylius.product.index', 'sylius.product_variant.index', 'sylius.product_variant_image.index'],
            ['catalog' => ['Products' => '/admin/products/']],
        );

        $nested = $this->subject($tree, 'catalog', 'sylius.product_variant_image');

        self::assertSame('sylius.product_variant', $nested->parent);
    }

    public function testItUsesADeclaredParentWhenTheIdentifierCannotExpressOne(): void
    {
        $tree = $this->tree(
            ['sylius.customer.index', 'sylius.shop_user.index'],
            ['customers' => ['Customers' => '/admin/customers/']],
            ['sylius.shop_user' => 'sylius.customer'],
        );

        self::assertSame('sylius.customer', $this->subject($tree, 'customers', 'sylius.shop_user')->parent);
    }

    public function testItIgnoresADeclaredParentThatNoPermissionMentions(): void
    {
        $tree = $this->tree(['sylius.shop_user.index'], [], ['sylius.shop_user' => 'sylius.nothing']);

        self::assertNull($this->subject($tree, 'not_on_the_menu', 'sylius.shop_user')->parent);
    }

    /** A heading for what nothing reaches is still better than dropping the permissions. */
    public function testItCollectsWhateverIsLeftOverLast(): void
    {
        $tree = $this->tree(
            ['sylius.product.index', 'sylius.live_component.execute'],
            ['catalog' => ['Products' => '/admin/products/']],
        );

        self::assertSame(['catalog', 'not_on_the_menu'], $this->groupNames($tree));
    }

    /** Groups are read where the menu puts them, not alphabetically. */
    public function testItOrdersGroupsAsTheyAppearInTheMenuRatherThanAlphabetically(): void
    {
        $tree = $this->tree(['sylius.order.index', 'sylius.product.index'], [
            'sales' => ['Orders' => '/admin/orders/'],
            'catalog' => ['Products' => '/admin/products/'],
        ]);

        self::assertSame(['sales', 'catalog'], $this->groupNames($tree));
    }

    /** A section whose own route needs no permission has no subject to derive a position from. */
    public function testItOrdersASectionByItsOwnPositionEvenWhenNoSubjectIsOnIt(): void
    {
        $tree = $this->tree(
            ['sylius.dashboard.view' => 'dashboard', 'sylius.product.index'],
            ['dashboard' => [], 'catalog' => ['Products' => '/admin/products/']],
        );

        self::assertSame(['dashboard', 'catalog'], $this->groupNames($tree));
    }

    /** Same idea one level down: the rows inside a group follow their entry's own menu order. */
    public function testItOrdersSubjectsWithinAGroupAsTheyAppearInTheMenu(): void
    {
        $tree = $this->tree(['sylius.order.index', 'sylius.customer.index'], [
            'sales' => [
                'Orders' => '/admin/orders/',
                'Customers' => '/admin/customers/',
            ],
        ]);

        self::assertSame(['Orders', 'Customers'], $this->labels($tree, 'sales'));
    }

    /**
     * A section name may arrive as the menu's translation key or as a declaration's own word.
     * Reducing both to one token is what keeps them from becoming two sections that read alike.
     */
    public function testItFilesTheMenusOwnTranslationKeyAndAPlainNameTogether(): void
    {
        $tree = $this->tree(
            ['sylius.product.index', 'sylius.live_component.execute' => 'catalog'],
            ['sylius.menu.admin.main.catalog.header' => ['Products' => '/admin/products/']],
        );

        self::assertSame(['catalog'], $this->groupNames($tree));
    }

    public function testItFallsBackToDeclarationsWhenThereIsNoMenuAtAll(): void
    {
        $tree = $this->tree(['sylius.product.index' => 'catalog'], null);

        self::assertSame(['catalog'], $this->groupNames($tree));
    }

    /**
     * @param array<int|string, string> $permissions identifier, or identifier => declared group
     * @param array<string, array<string, string>>|null $menu section => entry label => path
     * @param array<string, string> $subjectParents
     */
    private function tree(array $permissions, ?array $menu, array $subjectParents = []): PermissionTree
    {
        $definitions = [];

        foreach ($permissions as $key => $value) {
            $identifier = is_int($key) ? $value : $key;
            $definitions[$identifier] = new PermissionDefinition(
                PermissionIdentifier::fromString($identifier),
                group: is_int($key) ? null : $value,
            );
        }

        $registry = $this->createMock(PermissionRegistryInterface::class);
        $registry->method('all')->willReturn($definitions);

        $resources = $this->createMock(RegistryInterface::class);
        $resources->method('getAll')->willReturn(array_map(
            function (string $subjectKey, string $pluralName): MetadataInterface {
                [$applicationName, $name] = explode('.', $subjectKey);
                $metadata = $this->createMock(MetadataInterface::class);
                $metadata->method('getApplicationName')->willReturn($applicationName);
                $metadata->method('getName')->willReturn($name);
                $metadata->method('getPluralName')->willReturn($pluralName);

                return $metadata;
            },
            array_keys($this->pluralNames),
            array_values($this->pluralNames),
        ));

        return new PermissionTree(
            $registry,
            $this->router(),
            new RoutePermissionResolver(),
            $this->menuProvider($menu),
            $resources,
            $subjectParents,
        );
    }

    private function router(): RouterInterface
    {
        $collection = new RouteCollection();
        $matches = [];

        foreach (self::SCREENS as $path => $controller) {
            $name = 'route_' . count($matches);
            $collection->add($name, new Route($path, ['_controller' => $controller]));
            $matches[$path] = ['_route' => $name];
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);
        $router->method('match')->willReturnCallback(
            static fn (string $path): array => $matches[$path] ?? throw new \RuntimeException('No route for ' . $path),
        );

        return $router;
    }

    /** @param array<string, array<string, string>>|null $menu */
    private function menuProvider(?array $menu): UnfilteredMenuProviderInterface
    {
        $provider = $this->createMock(UnfilteredMenuProviderInterface::class);
        $provider->method('menu')->willReturn(null === $menu ? null : $this->menu($menu));

        return $provider;
    }

    /** @param array<string, array<string, string>> $sections */
    private function menu(array $sections): ItemInterface
    {
        $root = (new MenuFactory())->createItem('root');

        foreach ($sections as $section => $entries) {
            $header = $root->addChild($section)->setLabel($section);

            foreach ($entries as $label => $path) {
                $header->addChild($label)->setLabel($label)->setUri($path);
            }
        }

        return $root;
    }

    /** @return list<string> */
    private function groupNames(PermissionTree $tree): array
    {
        return array_map(static fn (PermissionGroup $group): string => $group->name, $tree->groups());
    }

    /** @return list<string> */
    private function labels(PermissionTree $tree, string $group): array
    {
        return array_map(static fn ($subject): string => $subject->label, $this->group($tree, $group)->subjects());
    }

    private function group(PermissionTree $tree, string $name): PermissionGroup
    {
        foreach ($tree->groups() as $group) {
            if ($group->name === $name) {
                return $group;
            }
        }

        self::fail(sprintf('No group "%s" in [%s].', $name, implode(', ', $this->groupNames($tree))));
    }

    private function subject(PermissionTree $tree, string $group, string $key)
    {
        foreach ($this->group($tree, $group)->subjects() as $subject) {
            if ($subject->key === $key) {
                return $subject;
            }
        }

        self::fail(sprintf('No subject "%s" in group "%s".', $key, $group));
    }
}
