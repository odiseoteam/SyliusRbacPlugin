<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler;

use Sylius\TwigHooks\Hookable\HookableComponent;
use Sylius\TwigHooks\Hookable\HookableTemplate;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds `configuration: {permission: ...}` to hookables Sylius already registered, from
 * `config/app/hookable_permissions.yaml`. `PermissionGatedHookableRenderer` reads it back at
 * render time.
 *
 * Done here rather than in `sylius_twig_hooks` config because a hookable declared there has to
 * carry its own template or component: adding only a `configuration` key means relying on
 * Sylius' declaration being merged with it, which holds in a Sylius application and nowhere else
 * -- the admin's hookables are imported by `config/packages/_sylius.yaml`, not by the bundle. It
 * also made every hook a version does not ship a compilation error, so gating one meant branching
 * the configuration on the installed Sylius version.
 *
 * Neither is true of a service that is simply not there: the entry is skipped. So is a
 * `DisabledHookable`, which Sylius leaves behind for a hookable it replaced and which has no
 * configuration to carry -- gating one would gate nothing.
 */
final class InjectHookablePermissionsPass implements CompilerPassInterface
{
    private const PARAMETER = 'odiseo_rbac.hookable_permissions';

    private const SERVICE_ID = 'sylius_twig_hooks.hook.%s.hookable.%s';

    private const CONFIGURATION_KEY = 'permission';

    /**
     * Where `configuration` sits in each hookable's arguments: a component takes `props` the
     * template does not, so the index is not the same for both.
     *
     * @var array<class-string, int>
     */
    private const CONFIGURATION_ARGUMENT = [
        HookableTemplate::class => 4,
        HookableComponent::class => 5,
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        /** @var array<string, array<string, list<string>>> $permissions */
        $permissions = $container->getParameter(self::PARAMETER);

        foreach ($permissions as $hook => $hookables) {
            foreach ($hookables as $hookable => $permission) {
                $this->gate($container, sprintf(self::SERVICE_ID, $hook, $hookable), $permission);
            }
        }
    }

    /** @param list<string> $permission */
    private function gate(ContainerBuilder $container, string $id, array $permission): void
    {
        if (!$container->hasDefinition($id)) {
            return;
        }

        $definition = $container->getDefinition($id);
        $index = self::CONFIGURATION_ARGUMENT[$definition->getClass()] ?? null;

        if (null === $index) {
            return;
        }

        /** @var array<string, mixed> $configuration */
        $configuration = $definition->getArgument($index);
        $configuration[self::CONFIGURATION_KEY] = $permission;

        $definition->replaceArgument($index, $configuration);
    }
}
