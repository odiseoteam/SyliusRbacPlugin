<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;

/**
 * Turns hand-written declarations into definitions.
 *
 * Feeds on two sources with the same shape: permissions declared in configuration — which is how
 * the routes Sylius leaves uncovered get names — and permissions declared with
 * `#[RbacPermission]` and collected at container build time.
 *
 * A malformed identifier is reported rather than thrown, for the same reason the
 * route discoverer does it: a typo in one plugin's configuration must not stop the application
 * from booting. It is still loud — the debug command prints it, and its `--strict` mode fails on it.
 */
final readonly class DeclaredPermissionDiscoverer implements PermissionDiscovererInterface
{
    /**
     * @param array<string, array{identifier: string, label?: string|null, group?: string|null, dangerous?: bool}> $declarations
     *        keyed by whatever declared it — a route name, or a `Class::method` — so the report
     *        can say where a bad declaration came from
     */
    public function __construct(private array $declarations = [])
    {
    }

    public function discover(): DiscoveredPermissions
    {
        $definitions = [];
        $unprotectedRoutes = [];

        foreach ($this->declarations as $source => $declaration) {
            try {
                $definitions[] = new PermissionDefinition(
                    PermissionIdentifier::fromString($declaration['identifier']),
                    $declaration['label'] ?? null,
                    $declaration['group'] ?? null,
                    $declaration['dangerous'] ?? false,
                );
            } catch (InvalidPermissionSyntaxException $exception) {
                $unprotectedRoutes[(string) $source] = $exception->getMessage();
            }
        }

        return new DiscoveredPermissions($definitions, $unprotectedRoutes);
    }
}
