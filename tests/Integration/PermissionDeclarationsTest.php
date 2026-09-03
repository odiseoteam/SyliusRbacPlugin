<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Integration;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\UnmappableRouteException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Guards the one corner of the design that is coupled to route names.
 *
 * Everything else is derived: identifiers come from resource metadata, groups from the menu.
 * The declarations exist for the routes that derivation cannot name, and they are hand-written
 * strings pointing at somebody else's routing table -- so they rot exactly when Sylius renames
 * something, which is also when nothing else notices.
 */
final class PermissionDeclarationsTest extends KernelTestCase
{
    /**
     * A rename upstream shows up as "a new route nothing covers", which is loud. The other half
     * of it -- the entry now pointing at nothing -- is silent, and it is the half that leaves a
     * permission granted to roles and attached to no screen.
     */
    public function testEveryDeclaredRouteStillExists(): void
    {
        self::assertSame([], $this->routesThatNoLongerExist('odiseo_rbac.route_identifiers'));
    }

    public function testEveryExcludedRouteStillExists(): void
    {
        self::assertSame([], $this->routesThatNoLongerExist('odiseo_rbac.excluded_routes'));
    }

    /**
     * `excluded_routes` is a route with no permission of its own by design, not a route the
     * plugin failed to cover. What tells the two apart is a route the resource controller
     * already checks by itself, which has no business being here: listing it would silence a
     * check that exists rather than document one that deliberately does not.
     *
     * `enforcesPermission()` alone is not that signal -- `sylius_admin_login` declares
     * `_sylius: permission: true` same as any resource route, but `SecurityController::
     * loginAction()` is a plain controller that never reads it, so nothing is actually checked.
     * `resolve()` is what tells the declared-but-unenforced case apart from the genuine one: it
     * throws for `loginAction`, exactly as `ResourceRoutePermissionDiscoverer` relies on it to.
     */
    public function testNoExcludedRouteSilencesACheckItAlreadyHad(): void
    {
        /** @var list<string> $excluded */
        $excluded = self::getContainer()->getParameter('odiseo_rbac.excluded_routes');
        $resolver = new RoutePermissionResolver();

        self::assertNotEmpty($excluded);

        foreach ($excluded as $name) {
            $route = $this->route($name);

            if (!$resolver->enforcesPermission($route)) {
                continue;
            }

            try {
                $resolver->resolve($route);
            } catch (UnmappableRouteException) {
                continue;
            }

            self::fail(sprintf(
                'Route "%s" is already checked by the resource controller, so listing it under ' .
                '"excluded_routes" turns an existing check off rather than declaring an uncovered ' .
                'route open.',
                $name,
            ));
        }
    }

    /**
     * A malformed identifier degrades to "route nothing covers" in the report, which reads like
     * a missing declaration rather than a typo in the one that is there. Here it is its own
     * failure, naming the route.
     */
    public function testEveryDeclaredIdentifierIsWellFormedAndReachesTheRegistry(): void
    {
        /** @var array<string, string> $identifiers */
        $identifiers = self::getContainer()->getParameter('odiseo_rbac.route_identifiers');
        /** @var PermissionRegistryInterface $registry */
        $registry = self::getContainer()->get(PermissionRegistryInterface::class);

        self::assertNotEmpty($identifiers);

        foreach ($identifiers as $route => $identifier) {
            PermissionIdentifier::fromString($identifier);

            self::assertArrayHasKey(
                $identifier,
                $registry->all(),
                sprintf('"%s" is declared for route "%s" but no role can be granted it.', $identifier, $route),
            );
        }
    }

    /** @return list<string> */
    private function routesThatNoLongerExist(string $parameter): array
    {
        /** @var array<array-key, string> $declared */
        $declared = self::getContainer()->getParameter($parameter);
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();

        $names = array_is_list($declared) ? $declared : array_keys($declared);

        return array_values(array_filter(
            $names,
            static fn (string $name): bool => null === $routes->get($name),
        ));
    }

    private function route(string $name): Route
    {
        $route = self::getContainer()->get(RouterInterface::class)->getRouteCollection()->get($name);

        self::assertNotNull($route, sprintf('Route "%s" is declared but does not exist.', $name));

        return $route;
    }
}
