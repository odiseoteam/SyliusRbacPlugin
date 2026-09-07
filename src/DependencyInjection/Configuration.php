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
     * `.actions` hook has no `back`/`list`, there being no Actions dropdown yet. A `private
     * const` can't express that, since it has to be a compile-time literal;
     * `ungatedActionHookables()` is where the two merge.
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
                            ->arrayNode('package')
                                ->info('Composer package the route needs to exist, for a route only some installations have. The declaration is dropped unless every package listed is installed, so it neither covers a route that is not there nor reports itself orphaned. A route that one plugin only registers when a second one is present needs both. Leave unset for a route that is always there.')
                                ->beforeNormalization()->castToArray()->end()
                                ->scalarPrototype()->end()
                            ->end()
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
                    ->info('Subjects that belong under another subject in the permission tree, for the cases the identifier cannot express or gets wrong. A subject whose identifier correctly extends its parent -- sylius.promotion_coupon under sylius.promotion -- is nested without being listed here. Defaults come from config/app/subject_parents.yaml, a real config source merged with whatever an app adds, not a schema default an app override would replace.')
                    ->useAttributeAsKey('subject')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('folded_api_subjects')
                    ->info('Resources with no admin screen of their own -- images, translations, provinces -- whose operations resolve to the parent\'s "update" or "show" instead of a permission of their own. Defaults come from config/app/folded_api_subjects.yaml, a real config source merged with whatever an app adds, not a schema default an app override would replace.')
                    ->useAttributeAsKey('subject')
                    ->scalarPrototype()->end()
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
                    ->info('Entity-autocomplete aliases fixed to one entity, mapped to the permission its own index screen already checks. See EntityAutocompletePermissionResolver. Defaults come from config/app/entity_autocomplete_permissions.yaml, a real config source merged with whatever an app adds, not a schema default an app override would replace.')
                    ->useAttributeAsKey('alias')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('live_component_permissions')
                    ->info('Live components mapped to the permission their own screen already checks. See LiveComponentPermissionResolver. Defaults come from config/app/live_component_permissions.yaml, a real config source merged with whatever an app adds, not a schema default an app override would replace.')
                    ->useAttributeAsKey('component')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('hookable_permissions')
                    ->info('Hookables Sylius already registered, mapped to the permission that has to be granted for them to render. Hook name, then hookable name, then one permission or a list granting on any of them. See InjectHookablePermissionsPass.')
                    ->useAttributeAsKey('hook')
                    ->arrayPrototype()
                        ->useAttributeAsKey('hookable')
                        ->arrayPrototype()
                            ->beforeNormalization()->castToArray()->end()
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
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
