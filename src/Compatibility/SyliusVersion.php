<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Compatibility;

/**
 * Whether the installed sylius/sylius is at least a given version.
 *
 * Exists because `Configuration.php` needs to know this before anything can inject it: its
 * test-only "ungated hookables" default lists two hookables that only exist from 2.1 on.
 */
final class SyliusVersion
{
    public static function isAtLeast(string $version): bool
    {
        $installed = \Composer\InstalledVersions::getVersion('sylius/sylius');

        return null !== $installed && version_compare($installed, $version, '>=');
    }
}
