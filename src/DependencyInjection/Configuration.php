<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('odiseo_sylius_rbac_plugin');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('sylius_sections')
                    ->children()
                        ->arrayNode('catalog_management')->variablePrototype()->end()->end()
                        ->arrayNode('configuration')->variablePrototype()->end()->end()
                        ->arrayNode('customers_management')->variablePrototype()->end()->end()
                        ->arrayNode('marketing_management')->variablePrototype()->end()->end()
                        ->arrayNode('sales_management')->variablePrototype()->end()->end()
                    ->end()
                ->end()
            ->end()
            ->children()
                /* it's a very MVP approach, as now we can pass almost everything there
                   TODO: create some more strict custom sections structure */
                ->arrayNode('custom_sections')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
