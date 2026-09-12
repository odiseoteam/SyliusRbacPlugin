<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DataMigration;

use Odiseo\SyliusRbacPlugin\DataMigration\LegacySectionPermissionTranslator;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class LegacySectionPermissionTranslatorTest extends TestCase
{
    private const ROUTES = [
        'sylius_admin_product_index' => 'sylius.controller.product::indexAction',
        'sylius_admin_product_show' => 'sylius.controller.product::showAction',
        'sylius_admin_product_update' => 'sylius.controller.product::updateAction',
        'sylius_admin_product_delete' => 'sylius.controller.product::deleteAction',
        'sylius_admin_product_review_index' => 'sylius.controller.product_review::indexAction',
        'sylius_admin_product_review_delete' => 'sylius.controller.product_review::deleteAction',
    ];

    private const SECTIONS = [
        'catalog_management' => ['sylius_admin_product'],
        'marketing_management' => ['sylius_admin_product_review'],
    ];

    public function testWriteBecomesAWildcardPerSubject(): void
    {
        self::assertSame(
            ['sylius.product.*'],
            $this->translator()->translate('catalog_management', true),
        );
    }

    /**
     * The asymmetry that matters. A legacy read grant did open the edit form, because that was
     * a GET, but could not submit it, because the PUT was a write. In v3 both halves are the
     * same `update` permission, so a wildcard here would turn every read-only administrator
     * into a writer.
     */
    public function testReadNeverBecomesAWildcard(): void
    {
        $patterns = $this->translator()->translate('catalog_management', false);

        self::assertSame(['sylius.product.index', 'sylius.product.show'], $patterns);
        self::assertNotContains('sylius.product.update', $patterns);
        self::assertNotContains('sylius.product.*', $patterns);
    }

    /**
     * `sylius_admin_product` is a prefix of `sylius_admin_product_review_index`, and the old
     * engine returned on the first section that matched — marketing was tested before catalog.
     * Matching every section that fits would hand a catalog role the product reviews.
     */
    public function testARouteBelongsToTheFirstSectionThatClaimsIt(): void
    {
        self::assertSame(
            ['sylius.product.*'],
            $this->translator()->translate('catalog_management', true),
        );

        self::assertSame(
            ['sylius.product_review.*'],
            $this->translator()->translate('marketing_management', true),
        );
    }

    /**
     * Custom sections were tested after all five built-in ones, so a custom prefix that
     * overlaps a built-in one never won. Reproduced rather than corrected: the roles being
     * migrated were granted under these rules, and "fixing" the precedence now would move
     * access between sections that nobody asked to have moved.
     */
    public function testACustomSectionLosesToABuiltInOneItOverlaps(): void
    {
        $translator = $this->translator([
            'catalog_management' => ['sylius_admin_product'],
            'loyalty' => ['sylius_admin_product_review'],
        ]);

        self::assertSame(
            ['sylius.product.*', 'sylius.product_review.*'],
            $translator->translate('catalog_management', true),
        );
        self::assertSame([], $translator->translate('loyalty', true));
    }

    /**
     * A section over invokable controllers is gated on its `route_permissions` declaration alone;
     * reading only the resource controller used to translate it to nothing.
     */
    public function testADeclaredRouteIsGrantedByWhatItDeclares(): void
    {
        $translator = $this->translator(
            ['unified_orders' => ['app_component_unified_orders']],
            ['app_component_unified_orders_admin_index' => 'app.unified_order.index'],
            ['app_component_unified_orders_admin_index' => 'App\\Controller\\UnifiedOrdersAction'],
        );

        self::assertSame(['app.unified_order.*'], $translator->translate('unified_orders', true));
        self::assertSame(['app.unified_order.index'], $translator->translate('unified_orders', false));
    }

    /**
     * Same rule as the runtime: a declaration wins over what the controller would have said.
     */
    public function testADeclarationWinsOverTheResourceController(): void
    {
        $translator = $this->translator(
            null,
            ['sylius_admin_product_index' => 'app.legacy_product.index'],
        );

        self::assertSame(
            ['app.legacy_product.index', 'sylius.product.show'],
            $translator->translate('catalog_management', false),
        );
    }

    public function testItKnowsWhichSectionsItCanTranslate(): void
    {
        self::assertTrue($this->translator()->knowsSection('catalog_management'));
        self::assertFalse($this->translator()->knowsSection('loyalty_management'));
    }

    /**
     * @param array<string, list<string>>|null $sections
     * @param array<string, string> $declared
     * @param array<string, string> $extraRoutes route name => controller
     */
    private function translator(
        ?array $sections = null,
        array $declared = [],
        array $extraRoutes = [],
    ): LegacySectionPermissionTranslator {
        $collection = new RouteCollection();

        foreach (self::ROUTES as $name => $controller) {
            $collection->add($name, new Route('/admin/whatever', [
                '_controller' => $controller,
                '_sylius' => ['permission' => true],
            ]));
        }

        foreach ($extraRoutes as $name => $controller) {
            $collection->add($name, new Route('/admin/whatever', ['_controller' => $controller]));
        }

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return new LegacySectionPermissionTranslator(
            $router,
            new RoutePermissionResolver(),
            $sections ?? self::SECTIONS,
            $declared,
        );
    }
}
