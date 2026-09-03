<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Some hooks this plugin gates simply do not exist on every supported Sylius version -- the
 * order screen's Actions dropdown and the dashboard's "pending actions" widget both arrived in
 * 2.1. Declaring a permission for a hook Sylius has not registered fails container compilation:
 * there is no template or component for ours to attach to, only the `configuration` we add.
 *
 * Kept in their own directory rather than alongside the always-safe declarations in
 * `app/twig_hooks/`, which every supported version imports unconditionally through the glob in
 * `config.yaml`.
 */
return static function (ContainerConfigurator $container): void {
    $syliusVersion = \Composer\InstalledVersions::getVersion('sylius/sylius');

    if (null !== $syliusVersion && version_compare($syliusVersion, '2.1.0', '>=')) {
        $container->import('app/twig_hooks_sylius_2_1/**/*.yaml');
    }
};
