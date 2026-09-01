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
     * say so on its own.
     *
     * Most sub-resources are derived instead: `sylius.product_taxon` extends `sylius.product`,
     * so the tree nests it without being told. `sylius.shop_user` is the exception — the
     * customer's login account, edited from the customer screen, sharing no prefix with it.
     *
     * @var array<string, string>
     */
    private const SUBJECT_PARENTS = [
        'sylius.shop_user' => 'sylius.customer',
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
                    ->info('Subjects that belong under another subject in the permission tree, for the cases the identifier cannot express. A subject whose identifier extends its parent -- sylius.product_taxon under sylius.product -- is nested without being listed here.')
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
            ->end()
        ;

        return $treeBuilder;
    }
}
