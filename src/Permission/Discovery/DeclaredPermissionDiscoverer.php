<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Symfony\Component\Routing\RouterInterface;

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
 *
 * A route-keyed declaration whose route no longer exists is dropped instead: the plugin it
 * belonged to was uninstalled, or Sylius renamed the route. Silently, because this is a routine
 * event, not a mistake -- `OrphanedRouteFinder` and `--strict` are where a rename that was never
 * followed up on gets caught. A `Class::method` source is never a route, so it is never checked
 * against the router.
 */
final readonly class DeclaredPermissionDiscoverer implements PermissionDiscovererInterface
{
    private const CLASS_METHOD_SEPARATOR = '::';

    /**
     * @param array<string, array{identifier: string, label?: string|null, group?: string|null}> $declarations
     *        keyed by whatever declared it — a route name, or a `Class::method` — so the report
     *        can say where a bad declaration came from
     */
    public function __construct(
        private RouterInterface $router,
        private array $declarations = [],
    ) {
    }

    public function discover(): DiscoveredPermissions
    {
        $definitions = [];
        $unprotectedRoutes = [];
        $routes = $this->router->getRouteCollection();

        foreach ($this->declarations as $source => $declaration) {
            $source = (string) $source;

            if (!str_contains($source, self::CLASS_METHOD_SEPARATOR) && null === $routes->get($source)) {
                continue;
            }

            try {
                $definitions[] = new PermissionDefinition(
                    PermissionIdentifier::fromString($declaration['identifier']),
                    $declaration['label'] ?? null,
                    $declaration['group'] ?? null,
                );
            } catch (InvalidPermissionSyntaxException $exception) {
                $unprotectedRoutes[$source] = $exception->getMessage();
            }
        }

        return new DiscoveredPermissions($definitions, $unprotectedRoutes);
    }
}
