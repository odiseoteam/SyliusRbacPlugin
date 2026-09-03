<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Compatibility;

/**
 * Whether the installed sylius/sylius is at least a given version.
 *
 * Exists because two places outside the container need to know this before anything can inject
 * it: `config/sylius_version_gate.php`, which decides whether to load twig hooks a version does
 * not have, and `Configuration.php`, whose test-only "ungated hookables" default lists two
 * hookables that only exist from 2.1 on. A single check keeps both from drifting.
 */
final class SyliusVersion
{
    public static function isAtLeast(string $version): bool
    {
        $installed = \Composer\InstalledVersions::getVersion('sylius/sylius');

        return null !== $installed && version_compare($installed, $version, '>=');
    }
}
