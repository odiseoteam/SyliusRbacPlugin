<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection;

use Odiseo\SyliusRbacPlugin\Compatibility\SyliusVersion;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * The section map the plugin shipped before v3, reproduced verbatim from the old
     * `src/Resources/config/config.yaml`.
     *
     * It is a default rather than a constant of the migration because an installation was free
     * to override it, and the override is what its stored roles actually mean.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_SYLIUS_SECTIONS = [
        'catalog_management' => [
            'sylius_admin_inventory',
            'sylius_admin_product',
            'sylius_admin_product_association_type',
            'sylius_admin_product_attribute',
            'sylius_admin_product_option',
            'sylius_admin_product_variant',
            'sylius_admin_taxon',
        ],
        'configuration' => [
            'sylius_admin_admin_user',
            'sylius_admin_channel',
            'sylius_admin_country',
            'sylius_admin_currency',
            'sylius_admin_exchange_rate',
            'sylius_admin_locale',
            'sylius_admin_payment_method',
            'sylius_admin_shipping_category',
            'sylius_admin_shipping_method',
            'sylius_admin_tax_category',
            'sylius_admin_tax_rate',
            'sylius_admin_zone',
        ],
        'customers_management' => [
            'sylius_admin_customer',
            'sylius_admin_customer_group',
            'sylius_admin_shop_user',
        ],
        'marketing_management' => [
            'sylius_admin_product_review',
            'sylius_admin_promotion',
            'sylius_admin_catalog_promotion',
        ],
        'sales_management' => [
            'sylius_admin_order',
            'sylius_admin_payment',
            'sylius_admin_shipment',
        ],
    ];

    /**
     * The plugin's own routes were a custom section called `rbac`. Both the old and the new
     * route names are listed: the old one is what pre-v3 installations configured, the new one
     * is what the router answers to today, and a role holding `rbac` has to end up able to
     * manage roles either way.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_CUSTOM_SECTIONS = [
        'rbac' => [
            'odiseo_sylius_rbac_plugin',
            'odiseo_rbac_admin_administration_role',
        ],
    ];

    /**
     * Subjects reached only from inside another subject's screen, where the identifier does not
     * say so on its own, or says so wrong.
     *
     * Most sub-resources are derived instead: `sylius.promotion_coupon` extends
     * `sylius.promotion`, so the tree nests it without being told. The rest share a prefix with
     * the wrong subject, because the identifier is built from the controller's service name, not
     * from where the screen is actually reached:
     *
     * - `sylius.shop_user` is the customer's login account, edited from the customer screen,
     *   sharing no prefix with it.
     * - `sylius.product_taxon` ("Manage product positions") is reached from editing a taxon, not
     *   a product, despite the `product_` prefix.
     * - `sylius.channel_pricing_log_entry` ("Price history") is reached from editing a product
     *   variant's price in one channel, not from managing channels, despite the `channel_`
     *   prefix.
     * - `sylius.impersonation` is a header button on the customer screen, right next to the one
     *   that deletes the shop user account — same screen as `sylius.shop_user`, sharing no
     *   prefix with either.
     *
     * @var array<string, string>
     */
    private const SUBJECT_PARENTS = [
        'sylius.shop_user' => 'sylius.customer',
        'sylius.product_taxon' => 'sylius.taxon',
        'sylius.channel_pricing_log_entry' => 'sylius.product_variant',
        'sylius.impersonation' => 'sylius.customer',
        // No single owner -- shared by a customer's address book and an order's addresses.
        'sylius.address' => 'sylius.customer',
    ];

    /**
     * API-only resources with no admin screen of their own
     *
     * @var array<string, string>
     */
    private const FOLDED_API_SUBJECTS = [
        'sylius.product_image' => 'sylius.product',
        'sylius.product_translation' => 'sylius.product',
        'sylius.product_association' => 'sylius.product',
        'sylius.taxon_image' => 'sylius.taxon',
        'sylius.taxon_translation' => 'sylius.taxon',
        'sylius.product_variant_translation' => 'sylius.product_variant',
        'sylius.channel_pricing' => 'sylius.product_variant',
        'sylius.product_attribute_value' => 'sylius.product_attribute',
        'sylius.product_attribute_translation' => 'sylius.product_attribute',
        'sylius.product_option_value' => 'sylius.product_option',
        'sylius.product_option_value_translation' => 'sylius.product_option',
        'sylius.product_option_translation' => 'sylius.product_option',
        'sylius.product_association_type_translation' => 'sylius.product_association_type',
        'sylius.province' => 'sylius.country',
        'sylius.promotion_rule' => 'sylius.promotion',
        'sylius.promotion_action' => 'sylius.promotion',
        'sylius.promotion_translation' => 'sylius.promotion',
        'sylius.catalog_promotion_action' => 'sylius.catalog_promotion',
        'sylius.catalog_promotion_scope' => 'sylius.catalog_promotion',
        'sylius.catalog_promotion_translation' => 'sylius.catalog_promotion',
        'sylius.shipping_method_rule' => 'sylius.shipping_method',
        'sylius.shipping_method_translation' => 'sylius.shipping_method',
        'sylius.payment_method_translation' => 'sylius.payment_method',
        'sylius.gateway_config' => 'sylius.payment_method',
        'sylius.avatar_image' => 'sylius.admin_user',
        'sylius.order_item' => 'sylius.order',
        'sylius.order_item_unit' => 'sylius.order',
        'sylius.adjustment' => 'sylius.order',
        'sylius.address_log_entry' => 'sylius.order',
    ];

    /**
     * `sylius_admin_entity_autocomplete` is one route shared by every autocomplete field, so no
     * single permission covers all of it. These aliases each query one fixed entity, so they get
     * the permission its own index screen already checks. The grid filter aliases are not
     * listed: they are reused across every grid, so their target travels with the request
     * instead — see `EntityAutocompletePermissionResolver`.
     *
     * @var array<string, string>
     */
    private const AUTOCOMPLETE_ALIAS_PERMISSIONS = [
        'sylius_admin_taxon' => 'sylius.taxon.index',
        'sylius_admin_product' => 'sylius.product.index',
        'sylius_admin_product_variant' => 'sylius.product_variant.index',
        'sylius_admin_product_attribute' => 'sylius.product_attribute.index',
    ];

    /**
     * `sylius_admin_live_component` is one route shared by every live component in the admin, so
     * no single permission covers all of it. Each is mapped here to the permission its own screen
     * already checks. `sylius_admin:taxon:tree` is not listed: `LiveComponentPermissionResolver`
     * gives it its own rule, because its default action only renders the tree but `moveUp` and
     * `moveDown` reorder it for real.
     *
     * The dashboard widgets are mapped to the resource each one is actually a summary of --
     * `count_shipments_to_ship` to `sylius.shipment.index`, not a blanket `sylius.dashboard.view`
     * -- so a role missing one resource's permission simply does not see that widget, rather than
     * a single dashboard permission gating everything or nothing. See
     * `config/app/twig_hooks/admin/dashboard.yaml`, where the same permissions gate the initial
     * render through `PermissionGatedHookableRenderer`; this map only covers the follow-up
     * live-action requests. `sylius_admin:dashboard:channel_selector` is not listed: it changes
     * no data of its own, so it is in `live_component_excluded` instead.
     *
     * @var array<string, string>
     */
    private const LIVE_COMPONENT_PERMISSIONS = [
        'sylius_admin:customer:order_statistics' => 'sylius.customer.show',
        'sylius_admin:dashboard:new_orders' => 'sylius.order.index',
        'sylius_admin:dashboard:statistics' => 'sylius.statistics.view',
        'sylius_admin:dashboard:pending_action:count_orders_to_process' => 'sylius.order.index',
        'sylius_admin:dashboard:pending_action:count_pending_payments' => 'sylius.payment.index',
        'sylius_admin:dashboard:pending_action:count_product_reviews_to_approve' => 'sylius.product_review.index',
        'sylius_admin:dashboard:pending_action:count_product_variants_out_of_stock' => 'sylius.product_variant.index',
        'sylius_admin:dashboard:pending_action:count_shipments_to_ship' => 'sylius.shipment.index',
        'sylius_admin:admin_user:form' => 'sylius.admin_user.update',
        'sylius_admin:catalog_promotion:form' => 'sylius.catalog_promotion.update',
        'sylius_admin:channel:form' => 'sylius.channel.update',
        'sylius_admin:country:form' => 'sylius.country.update',
        'sylius_admin:currency:form' => 'sylius.currency.update',
        'sylius_admin:customer:form' => 'sylius.customer.update',
        'sylius_admin:customer_group:form' => 'sylius.customer_group.update',
        'sylius_admin:exchange_rate:form' => 'sylius.exchange_rate.update',
        'sylius_admin:locale:form' => 'sylius.locale.update',
        'sylius_admin:order:form' => 'sylius.order.update',
        'sylius_admin:product:form' => 'sylius.product.update',
        'sylius_admin:product:generate_product_variants_form' => 'sylius.product.update',
        'sylius_admin:product:product_attribute_autocomplete' => 'sylius.product.update',
        'sylius_admin:product_association_type:form' => 'sylius.product_association_type.update',
        'sylius_admin:product_attribute:form' => 'sylius.product_attribute.update',
        'sylius_admin:product_option:form' => 'sylius.product_option.update',
        'sylius_admin:product_review:form' => 'sylius.product_review.update',
        'sylius_admin:product_variant:form' => 'sylius.product_variant.update',
        'sylius_admin:promotion:form' => 'sylius.promotion.update',
        'sylius_admin:promotion_coupon:form' => 'sylius.promotion_coupon.update',
        'sylius_admin:promotion_coupon:generator_instruction_form' => 'sylius.promotion_coupon.generate',
        'sylius_admin:shipment:ship_form' => 'sylius.shipment.ship',
        'sylius_admin:shipping_category:form' => 'sylius.shipping_category.update',
        'sylius_admin:shipping_method:form' => 'sylius.shipping_method.update',
        'sylius_admin:tax_category:form' => 'sylius.tax_category.update',
        'sylius_admin:tax_rate:form' => 'sylius.tax_rate.update',
        'sylius_admin:taxon:delete' => 'sylius.taxon.delete',
        'sylius_admin:taxon:form' => 'sylius.taxon.update',
        'sylius_admin:zone:form' => 'sylius.zone.update',
    ];

    /**
     * Hookables under an "actions" hook that deliberately carry no permission: form controls
     * (`update`, `cancel`...), navigation (`list`, `show`, `view_in_store`), and the catalog
     * promotion's discount rows, which aren't action buttons despite the hook name. Listed so a
     * new one from Sylius fails the build instead of going unnoticed.
     *
     * @var array<string, list<string>>
     */
    private const UNGATED_ACTION_HOOKABLES = [
        'sylius_admin.common.create.content.header.title_block.actions' => ['cancel', 'create'],
        'sylius_admin.common.update.content.header.title_block.actions' => ['cancel', 'update', 'show'],
        'sylius_admin.product.update.content.header.title_block.actions' => ['cancel', 'update', 'view_in_store'],
        'sylius_admin.product.show.content.header.title_block.actions' => ['view_in_store'],
        'sylius_admin.product_variant.update.content.header.title_block.actions' => ['cancel', 'update', 'view_in_store'],
        'sylius_admin.product.generate_variants.content.header.title_block.actions' => ['cancel', 'generate'],
        'sylius_admin.promotion_coupon.generate.content.header.title_block.actions' => ['cancel', 'generate'],
        'sylius_admin.order.history.content.header.title_block.actions' => ['back'],
        'sylius_admin.catalog_promotion.show.content.sections.actions' => ['action'],
        'sylius_admin.catalog_promotion.show.content.sections.actions.action#percentage_discount' => ['type', 'amount'],
        'sylius_admin.catalog_promotion.show.content.sections.actions.action#fixed_discount' => ['type', 'channels_amount'],
    ];

    /**
     * Ungated hookables that only exist from Sylius 2.1 on: `order/index.yaml` -- the order
     * grid's own cancel button -- does not ship before 2.1, and before then the order screen's
     * `.actions` hook has no `back`/`list` (no Actions dropdown yet, see
     * `config/app/twig_hooks_sylius_2_1/admin/order.yaml`). A `private const` can't express
     * that, since it has to be a compile-time literal; `ungatedActionHookables()` is where the
     * two merge.
     *
     * @var array<string, list<string>>
     */
    private const UNGATED_ACTION_HOOKABLES_SYLIUS_2_1 = [
        'sylius_admin.order.index.content.header.title_block.actions' => ['cancel'],
        'sylius_admin.order.show.content.header.title_block.actions' => ['back', 'list'],
    ];

    /** @return array<string, list<string>> */
    private static function ungatedActionHookables(): array
    {
        if (!SyliusVersion::isAtLeast('2.1.0')) {
            return self::UNGATED_ACTION_HOOKABLES;
        }

        return [...self::UNGATED_ACTION_HOOKABLES, ...self::UNGATED_ACTION_HOOKABLES_SYLIUS_2_1];
    }

    /**
     * Live components that deliberately require no permission -- the counterpart to
     * `excluded_routes`, for the fourth kind of route (see `AdminRouteAuthorizationListener`).
     *
     * `channel_selector` only changes which channel the *other* dashboard widgets are filtered
     * by; it exposes nothing and mutates nothing on its own, so nothing needs to be granted to
     * use it once the dashboard itself is reachable.
     *
     * @var list<string>
     */
    private const LIVE_COMPONENT_EXCLUDED = [
        'sylius_admin:dashboard:channel_selector',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('odiseo_sylius_rbac');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->addDefaultsIfNotSet();

        $rootNode
            ->children()
                ->arrayNode('route_permissions')
                    ->info('Maps admin routes to the permission they require. Ships with the routes Sylius leaves uncovered; applications and plugins add their own.')
                    ->useAttributeAsKey('route')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('permission')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Permission identifier, "{package}.{subject}.{operation}".')
                            ->end()
                            ->scalarNode('label')->defaultNull()->end()
                            ->scalarNode('group')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('sylius_sections')
                    ->info('Pre-v3 map of section name to admin route name prefixes. Read only by odiseo:rbac:migrate-permissions, to know what each stored section used to reach. Removed in 4.0.')
                    ->useAttributeAsKey('section')
                    ->arrayPrototype()->scalarPrototype()->end()->end()
                    ->defaultValue(self::LEGACY_SYLIUS_SECTIONS)
                ->end()
                ->arrayNode('custom_sections')
                    ->info('Pre-v3 sections declared by the application or by third-party plugins, in the same shape as sylius_sections. Still accepted so an upgrading application boots with its old configuration in place, and so roles holding a custom section migrate to the routes it actually covered. Removed in 4.0.')
                    ->useAttributeAsKey('section')
                    ->arrayPrototype()->scalarPrototype()->end()->end()
                    ->defaultValue(self::LEGACY_CUSTOM_SECTIONS)
                ->end()
                ->arrayNode('subject_parents')
                    ->info('Subjects that belong under another subject in the permission tree, for the cases the identifier cannot express or gets wrong. A subject whose identifier correctly extends its parent -- sylius.promotion_coupon under sylius.promotion -- is nested without being listed here.')
                    ->useAttributeAsKey('subject')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::SUBJECT_PARENTS)
                ->end()
                ->arrayNode('folded_api_subjects')
                    ->info('Resources with no admin screen of their own -- images, translations, provinces -- whose operations resolve to the parent\'s "update" or "show" instead of a permission of their own.')
                    ->useAttributeAsKey('subject')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::FOLDED_API_SUBJECTS)
                ->end()
                ->booleanNode('deny_unprotected_admin_routes')
                    ->info('Deny any admin route that no permission covers. On by default: a route nobody protected is the failure this plugin exists to prevent. Turn it off to let uncovered routes through while migrating an application that has many of them.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('excluded_routes')
                    ->info('Admin routes that deliberately require no permission, such as login and password reset. Listing them is what lets a coverage check tell "decided to leave open" apart from "forgot".')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('entity_autocomplete_permissions')
                    ->info('Entity-autocomplete aliases fixed to one entity, mapped to the permission its own index screen already checks. See EntityAutocompletePermissionResolver.')
                    ->useAttributeAsKey('alias')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::AUTOCOMPLETE_ALIAS_PERMISSIONS)
                ->end()
                ->arrayNode('live_component_permissions')
                    ->info('Live components mapped to the permission their own screen already checks. See LiveComponentPermissionResolver.')
                    ->useAttributeAsKey('component')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::LIVE_COMPONENT_PERMISSIONS)
                ->end()
                ->arrayNode('ungated_action_hookables')
                    ->info('Hookables under an "actions" hook that deliberately carry no permission. Listing them is what lets a coverage check tell a decision apart from an oversight.')
                    ->useAttributeAsKey('hook')
                    ->arrayPrototype()->scalarPrototype()->end()->end()
                    ->defaultValue(self::ungatedActionHookables())
                ->end()
                ->arrayNode('live_component_excluded')
                    ->info('Live components that deliberately require no permission, the live-component counterpart to "excluded_routes".')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::LIVE_COMPONENT_EXCLUDED)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
