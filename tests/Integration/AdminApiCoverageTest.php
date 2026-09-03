<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Integration;

use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistryInterface;
use Odiseo\SyliusRbacPlugin\Security\Api\ApiOperationPermissionResolverInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The admin API, asked the same question as the admin screens.
 *
 * This is where the plugin's worst bug lived: the pre-v3 engine matched route names beginning
 * with `sylius_admin`, so nothing under `sylius_api_admin_*` was ever checked. The runtime
 * listener now denies whatever it cannot resolve, which is safe but late -- an endpoint nobody
 * can name becomes a 403 no role can lift. Asking here turns that into a red build.
 */
final class AdminApiCoverageTest extends KernelTestCase
{
    /**
     * Every operation resolves to an identifier a role can actually hold. Both halves matter:
     * an unresolved operation is unreachable for everyone, and an identifier missing from the
     * registry is unreachable for everyone but looks granted.
     */
    public function testEveryAdminApiOperationResolvesToAGrantablePermission(): void
    {
        /** @var ApiOperationPermissionResolverInterface $resolver */
        $resolver = self::getContainer()->get(ApiOperationPermissionResolverInterface::class);
        /** @var PermissionRegistryInterface $registry */
        $registry = self::getContainer()->get(PermissionRegistryInterface::class);

        /** @var list<string> $excluded */
        $excluded = self::getContainer()->getParameter('odiseo_rbac.excluded_routes');
        /** @var array<string, string> $declared */
        $declared = self::getContainer()->getParameter('odiseo_rbac.route_identifiers');
        $operations = 0;

        foreach ($this->adminApiRoutes() as $name => $route) {
            $resourceClass = $route->getDefault('_api_resource_class');
            $operationName = $route->getDefault('_api_operation_name');

            if (!is_string($resourceClass) || !is_string($operationName)) {
                continue;
            }

            // Declared open on purpose, so there is no permission to resolve: the API's own
            // password reset is an operation, but it is reached without an administrator.
            if (in_array($name, $excluded, true)) {
                continue;
            }

            ++$operations;

            // A declaration wins over what the operation derives, the order the listener
            // enforces in. Asked the same way here so the two cannot disagree about which
            // permission an endpoint actually requires.
            $permission = $declared[$name] ?? $resolver->resolve($resourceClass, $operationName);

            self::assertNotNull(
                $permission,
                sprintf('No permission could be resolved for admin API route "%s".', $name),
            );
            self::assertArrayHasKey(
                $permission,
                $registry->all(),
                sprintf('"%s" is required by route "%s" but no role can be granted it.', $permission, $name),
            );
        }

        self::assertGreaterThan(0, $operations, 'no admin API operation was found at all');
    }

    /**
     * `/api/v2/admin/statistics` is why this exists: a plain controller, not an API Platform
     * operation, so there was no resource to derive a permission from and every administrator
     * with a token could read the shop's whole turnover. Anything else built that way has to be
     * named in configuration, the same as an uncovered HTML route.
     */
    public function testEveryPlainControllerUnderTheAdminApiIsDeclaredOrLeftOpenOnPurpose(): void
    {
        /** @var array<string, string> $declared */
        $declared = self::getContainer()->getParameter('odiseo_rbac.route_identifiers');
        /** @var list<string> $excluded */
        $excluded = self::getContainer()->getParameter('odiseo_rbac.excluded_routes');

        foreach ($this->adminApiRoutes() as $name => $route) {
            if (is_string($route->getDefault('_api_resource_class'))) {
                continue;
            }

            self::assertTrue(
                isset($declared[$name]) || in_array($name, $excluded, true),
                sprintf(
                    'Route "%s" (%s) is a plain controller under the admin API, so nothing derives ' .
                    'a permission for it. Declare one under "odiseo_sylius_rbac.route_permissions", ' .
                    'or list it under "excluded_routes".',
                    $name,
                    $route->getPath(),
                ),
            );
        }
    }

    /**
     * `FOLDED_API_SUBJECTS` exists so a subject with no screen of its own -- an image, a
     * translation, a province -- never becomes a row in the tree that looks exactly like a real
     * one and does nothing when unchecked. Checked here because the fold happens in the
     * resolver, and nothing stops a route being added later for one of these that the resolver
     * derives independently and slips back in as its own subject.
     */
    public function testNoFoldedSubjectReappearsAsAPermissionOfItsOwn(): void
    {
        /** @var array<string, string> $folded */
        $folded = self::getContainer()->getParameter('odiseo_rbac.folded_api_subjects');
        /** @var PermissionRegistryInterface $registry */
        $registry = self::getContainer()->get(PermissionRegistryInterface::class);

        self::assertNotEmpty($folded);

        foreach (array_keys($registry->all()) as $identifier) {
            $subject = implode('.', array_slice(explode('.', $identifier), 0, 2));

            self::assertArrayNotHasKey(
                $subject,
                $folded,
                sprintf(
                    '"%s" is declared under "folded_api_subjects", meant to fold into "%s", but it is ' .
                    'still granted on its own as "%s".',
                    $subject,
                    $folded[$subject] ?? '?',
                    $identifier,
                ),
            );
        }
    }

    /** @return iterable<string, Route> */
    private function adminApiRoutes(): iterable
    {
        $container = self::getContainer();
        /** @var string $apiPrefix */
        $apiPrefix = $container->getParameter('sylius.security.api_route');
        /** @var string $adminPathName */
        $adminPathName = $container->getParameter('sylius_admin.path_name');

        // The same prefix AdminApiAuthorizationListener matches on, read from the same
        // parameters, so the two cannot disagree about what "the admin API" is.
        $prefix = rtrim($apiPrefix, '/') . '/' . $adminPathName . '/';

        foreach ($container->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (str_starts_with($route->getPath(), $prefix)) {
                yield (string) $name => $route;
            }
        }
    }
}
