<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Integration;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * The test that keeps the plugin honest about its own premise: every way into the admin is
 * either behind a permission or written down as deliberately open.
 *
 * It boots the real kernel rather than a fixture, because the thing under test is the routing
 * table an installation actually has -- a route only this application declares is exactly the
 * case a hand-written list of expected routes would miss.
 */
final class AdminRouteCoverageTest extends KernelTestCase
{
    /**
     * The failure this plugin exists to prevent, asserted directly. `odiseo:rbac:debug --strict`
     * reports the same thing from the same discoverer; this is here so the invariant survives
     * the command being changed, moved or dropped from CI.
     */
    public function testEveryAdminRouteIsEitherCoveredByAPermissionOrDeclaredOpen(): void
    {
        $discovered = $this->discoverer()->discover();

        self::assertSame(
            [],
            $discovered->unprotectedRoutes,
            sprintf(
                "These admin routes are reachable without any permission check:\n%s\n" .
                'Declare each one under "odiseo_sylius_rbac.route_permissions", or list it under ' .
                '"excluded_routes" if leaving it open is the decision.',
                implode("\n", array_map(
                    static fn (string $route, string $why): string => sprintf('  - %s: %s', $route, $why),
                    array_keys($discovered->unprotectedRoutes),
                    $discovered->unprotectedRoutes,
                )),
            ),
        );
    }

    /**
     * `/administration-roles/` is covered by the admin firewall by coincidence: `^/admin` matches
     * the first five letters of "administration". Renaming the path to something like
     * `/rbac-roles/` would drop it out of the firewall and out of the ROLE_ADMINISTRATION_ACCESS
     * rule without a single test noticing. This turns the coincidence into a checked fact.
     */
    public function testEveryRouteThePluginDeclaresLivesUnderTheAdminFirewallPattern(): void
    {
        /** @var string $adminRegex */
        $adminRegex = self::getContainer()->getParameter('sylius.security.admin_regex');

        $routes = iterator_to_array($this->pluginRoutes());

        // Guards the two assertions below from passing on an empty set, which is what a rename
        // of the plugin's route prefix would otherwise silently produce.
        self::assertNotEmpty($routes, 'no route of the plugin was found at all');

        foreach ($routes as $name => $route) {
            self::assertMatchesRegularExpression(
                '{' . $adminRegex . '}',
                $route->getPath(),
                sprintf('Route "%s" (%s) falls outside the admin firewall pattern.', $name, $route->getPath()),
            );
        }
    }

    /**
     * The stronger half of the same question: matching the firewall pattern is not the same as
     * being covered by an access_control rule, and it is the rule that produces the redirect to
     * the login screen. Asked of the access map so the answer comes from the security
     * configuration the application booted with, not from re-reading the regex.
     */
    public function testEveryRouteThePluginDeclaresRequiresAnAuthenticatedAdministrator(): void
    {
        foreach ($this->pluginRoutes() as $name => $route) {
            self::assertContains(
                'ROLE_ADMINISTRATION_ACCESS',
                $this->accessAttributesFor($route),
                sprintf('Route "%s" (%s) is reachable without logging into the admin.', $name, $route->getPath()),
            );
        }
    }

    /** @return iterable<string, Route> */
    private function pluginRoutes(): iterable
    {
        foreach (self::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (str_starts_with((string) $name, 'odiseo_rbac')) {
                yield (string) $name => $route;
            }
        }
    }

    /** @return list<string> */
    private function accessAttributesFor(Route $route): array
    {
        /** @var AccessMapInterface $accessMap */
        $accessMap = self::getContainer()->get('security.access_map');

        // Any value satisfies an access_control rule: they match on the path, not on what a
        // placeholder happens to hold.
        $path = preg_replace('/\{[^}]+\}/', '1', $route->getPath()) ?? $route->getPath();

        return $accessMap->getPatterns(Request::create($path))[0] ?? [];
    }

    private function discoverer(): PermissionDiscovererInterface
    {
        /** @var PermissionDiscovererInterface $discoverer */
        $discoverer = self::getContainer()->get('odiseo_rbac.permission.discoverer');

        return $discoverer;
    }
}
