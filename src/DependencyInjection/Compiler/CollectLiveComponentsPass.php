<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects every admin live component name Sylius (or a plugin) registered.
 *
 * `sylius.live_component.admin` is Sylius' own tag for these, carrying the component's name
 * under `key` — the same string `{_live_component}` resolves to at runtime. Reading it here
 * rather than hand-listing components is what lets a plugin's own live component show up in
 * `odiseo:rbac:debug` without this plugin knowing it exists.
 */
final class CollectLiveComponentsPass implements CompilerPassInterface
{
    private const TAG = 'sylius.live_component.admin';

    public function process(ContainerBuilder $container): void
    {
        $components = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $tags) {
            foreach ($tags as $attributes) {
                if (isset($attributes['key']) && is_string($attributes['key'])) {
                    $components[] = $attributes['key'];
                }
            }
        }

        $container->setParameter('odiseo_rbac.live_components', array_values(array_unique($components)));
    }
}
