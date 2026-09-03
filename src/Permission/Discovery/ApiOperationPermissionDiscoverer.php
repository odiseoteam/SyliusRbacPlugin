<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Security\Api\ApiOperationPermissionResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adds what the admin API asks for to the vocabulary.
 *
 * The HTML admin is not the whole surface. The API exposes records with no screen of their own
 * -- provinces, taxon images, translations -- and exposes `show` for resources whose CRUD
 * declares `except: ['show']`. Those permissions are checked at runtime either way, so leaving
 * them out of the registry does not make them unnecessary: it makes them ungrantable, because
 * the role editor can only offer what the registry knows. The only way to hold one was to type
 * a wildcard by hand.
 *
 * The identifiers come from the same resolver the API listener enforces with, so the vocabulary
 * cannot drift from what is actually asked for.
 */
final readonly class ApiOperationPermissionDiscoverer implements PermissionDiscovererInterface
{
    /**
     * @param list<string> $handledRoutes routes already covered by a declaration or left open,
     *        passed over so the report only names operations needing attention
     */
    public function __construct(
        private RouterInterface $router,
        private ApiOperationPermissionResolverInterface $resolver,
        private array $handledRoutes = [],
        private string $apiRoutePrefix = '/api/v2',
        private string $adminPathName = 'admin',
    ) {
    }

    public function discover(): DiscoveredPermissions
    {
        $definitions = [];
        $unprotectedRoutes = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            $name = (string) $name;

            if (!$this->isAdminApiRoute($route) || in_array($name, $this->handledRoutes, true)) {
                continue;
            }

            $resourceClass = $route->getDefault('_api_resource_class');
            $operationName = $route->getDefault('_api_operation_name');

            /**
             * A plain controller mounted under the admin API, such as the statistics endpoint.
             * There is no resource to derive anything from, so it has to be declared -- which
             * `handledRoutes` above already accounted for. Reaching here means it was not.
             */
            if (!is_string($resourceClass) || !is_string($operationName)) {
                $unprotectedRoutes[$name] = 'is under the admin API but is not an operation anything can name';

                continue;
            }

            $permission = $this->resolver->resolve($resourceClass, $operationName);

            if (null === $permission) {
                $unprotectedRoutes[$name] = 'is an admin API operation no permission could be resolved for';

                continue;
            }

            $definitions[] = new PermissionDefinition(PermissionIdentifier::fromString($permission));
        }

        return new DiscoveredPermissions($definitions, $unprotectedRoutes);
    }

    private function isAdminApiRoute(Route $route): bool
    {
        return str_starts_with(
            $route->getPath(),
            rtrim($this->apiRoutePrefix, '/') . '/' . $this->adminPathName . '/',
        );
    }
}
