<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection;

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
     * @var array<string, string>
     */
    private const LIVE_COMPONENT_PERMISSIONS = [
        'sylius_admin:customer:order_statistics' => 'sylius.customer.show',
        'sylius_admin:dashboard:channel_selector' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:new_orders' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:statistics' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:pending_action:count_orders_to_process' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:pending_action:count_pending_payments' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:pending_action:count_product_reviews_to_approve' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:pending_action:count_product_variants_out_of_stock' => 'sylius.dashboard.view',
        'sylius_admin:dashboard:pending_action:count_shipments_to_ship' => 'sylius.dashboard.view',
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
            ->end()
        ;

        return $treeBuilder;
    }
}
