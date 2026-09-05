<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection;

use Composer\InstalledVersions;
use Odiseo\SyliusRbacPlugin\Permission\EntityAutocompletePermissionResolverInterface;
use Odiseo\SyliusRbacPlugin\Permission\LiveComponentPermissionResolverInterface;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

final class OdiseoSyliusRbacExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    private const ROUTE_PERMISSIONS_FILE = __DIR__ . '/../../config/app/route_permissions.yaml';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $config['route_permissions'] = self::installedOnly($config['route_permissions']);

        /**
         * What `installedOnly()` just read. Without it, adding or removing a plugin whose routes
         * are declared leaves the container holding the answer from before.
         */
        $container->addResource(new ComposerResource());

        $container->setParameter('odiseo_rbac.route_permissions', $config['route_permissions']);
        $container->setParameter('odiseo_rbac.excluded_routes', $config['excluded_routes']);
        $container->setParameter('odiseo_rbac.entity_autocomplete_permissions', $config['entity_autocomplete_permissions']);
        $container->setParameter('odiseo_rbac.live_component_permissions', $config['live_component_permissions']);
        $container->setParameter('odiseo_rbac.live_component_excluded', $config['live_component_excluded']);
        $container->setParameter('odiseo_rbac.ungated_action_hookables', $config['ungated_action_hookables']);
        $container->setParameter('odiseo_rbac.hookable_permissions', $config['hookable_permissions']);
        $container->setParameter('odiseo_rbac.mapped_live_components', [
            ...array_keys($config['live_component_permissions']),
            ...$config['live_component_excluded'],
            LiveComponentPermissionResolverInterface::TAXON_TREE_COMPONENT,
        ]);
        $container->setParameter('odiseo_rbac.declared_permissions', self::asDeclarations($config['route_permissions']));
        $container->setParameter('odiseo_rbac.deny_unprotected_admin_routes', $config['deny_unprotected_admin_routes']);
        $container->setParameter('odiseo_rbac.subject_parents', $config['subject_parents']);
        $container->setParameter('odiseo_rbac.folded_api_subjects', $config['folded_api_subjects']);
        $container->setParameter('odiseo_rbac.route_identifiers', self::asIdentifiers($config['route_permissions']));
        $container->setParameter('odiseo_rbac.legacy_section_routes', [
            ...$config['sylius_sections'],
            ...$config['custom_sections'],
        ]);
        $container->setParameter('odiseo_rbac.handled_routes', [
            ...array_keys($config['route_permissions']),
            ...$config['excluded_routes'],
            // Resolved per request rather than declared, so the discoverer would otherwise
            // report them as uncovered. See EntityAutocompletePermissionResolver and
            // LiveComponentPermissionResolver.
            EntityAutocompletePermissionResolverInterface::ROUTE,
            LiveComponentPermissionResolverInterface::ROUTE,
        ]);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.yaml');
    }

    /**
     * Drops the declarations whose packages this installation does not have.
     *
     * A route only some installations ship -- the payment plugins `sylius-standard` bundles, a
     * plugin an application chose not to install -- is declared with the packages it needs to
     * exist. Without them there is no route, so the declaration covers nothing and would report
     * itself orphaned on every `odiseo:rbac:debug`, in an application that did nothing wrong.
     *
     * All of them, not any: a route one plugin registers only while a second one is installed --
     * Mollie's refund screen, which lives under its own `integration/refund-plugin/` -- is gone
     * as soon as either is. The package that ships the routing file is not always the one that
     * decides whether it loads, so check where the route is declared rather than whose name it
     * carries.
     *
     * Only absence excuses a declaration: with the packages installed it is kept and checked like
     * any other, so a route one of those plugins renames still surfaces as orphaned. Removing a
     * plugin whose permissions roles already hold is caught by `OrphanedRolePermissionFinder`
     * instead, which is the half of it that needs acting on.
     *
     * @param array<string, array{permission: string, label: string|null, group: string|null, package: list<string>}> $routePermissions
     *
     * @return array<string, array{permission: string, label: string|null, group: string|null, package: list<string>}>
     */
    private static function installedOnly(array $routePermissions): array
    {
        return array_filter(
            $routePermissions,
            static fn (array $permission): bool => [] === array_filter(
                $permission['package'],
                static fn (string $package): bool => !InstalledVersions::isInstalled($package),
            ),
        );
    }

    /**
     * @param array<string, array{permission: string, label: string|null, group: string|null}> $routePermissions
     *
     * @return array<string, array{identifier: string, label: string|null, group: string|null}>
     */
    private static function asDeclarations(array $routePermissions): array
    {
        $declarations = [];

        foreach ($routePermissions as $route => $permission) {
            $declarations[$route] = [
                'identifier' => $permission['permission'],
                'label' => $permission['label'],
                'group' => $permission['group'],
            ];
        }

        return $declarations;
    }

    /**
     * @param array<string, array{permission: string, label: string|null, group: string|null}> $routePermissions
     *
     * @return array<string, string>
     */
    private static function asIdentifiers(array $routePermissions): array
    {
        return array_map(
            static fn (array $permission): string => $permission['permission'],
            $routePermissions,
        );
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        /**
         * Loaded here rather than left to the application's imports on purpose. Everything else
         * the plugin ships degrades gracefully if `config/config.yaml` is not imported — a grid
         * goes missing and someone notices. These declarations are the only thing standing
         * between an administrator and the impersonation endpoint, so they cannot be optional.
         *
         * Prepending also means the application still overrides any single entry by key.
         */
        /**
         * Tracked explicitly: the file is read here instead of being loaded through a loader,
         * so nothing else tells the container to rebuild when a declaration changes. Without
         * this, editing a permission has no effect until the cache is cleared by hand.
         */
        $container->addResource(new FileResource(self::ROUTE_PERMISSIONS_FILE));

        $defaults = Yaml::parseFile(self::ROUTE_PERMISSIONS_FILE);

        if (!is_array($defaults) || !is_array($defaults['odiseo_sylius_rbac'] ?? null)) {
            throw new \LogicException(sprintf(
                'Expected "%s" to declare an "odiseo_sylius_rbac" section.',
                self::ROUTE_PERMISSIONS_FILE,
            ));
        }

        $container->prependExtensionConfig('odiseo_sylius_rbac', $defaults['odiseo_sylius_rbac']);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Odiseo\SyliusRbacPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@OdiseoSyliusRbacPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
