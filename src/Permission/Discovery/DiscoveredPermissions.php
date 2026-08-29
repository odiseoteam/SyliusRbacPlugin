<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;

/**
 * What a discoverer found, and which admin routes nothing ends up checking.
 *
 * An unprotected route is reported, never thrown: one odd third-party controller must not stop
 * the application from booting. The fix for any of them is an entry in
 * `odiseo_sylius_rbac.route_permissions`, or in `public_routes` if leaving it open is intended.
 */
final readonly class DiscoveredPermissions
{
    /**
     * @param list<PermissionDefinition> $definitions
     * @param array<string, string> $unprotectedRoutes route name => why nothing checks it
     */
    public function __construct(
        public array $definitions = [],
        public array $unprotectedRoutes = [],
    ) {
    }
}
