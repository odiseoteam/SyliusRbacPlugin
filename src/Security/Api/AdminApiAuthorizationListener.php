<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Api;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Applies administration role permissions to the admin API.
 *
 * The pre-v3 engine matched route names beginning with `sylius_admin`, so none of the
 * `sylius_api_admin_*` routes ever reached a check and any administrator holding a JWT could do
 * anything. This closes that, using the same identifiers as the HTML admin.
 *
 * Denying here raises `AccessDeniedException`, which API Platform renders as a 403 JSON
 * response — never the login redirect an HTML firewall would produce.
 */
final readonly class AdminApiAuthorizationListener implements EventSubscriberInterface
{
    /**
     * @param array<string, string> $routePermissions route name => permission identifier, for
     *        the endpoints that are plain controllers rather than API Platform operations
     * @param list<string> $excludedRoutes
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private ApiOperationPermissionResolverInterface $resolver,
        private array $routePermissions = [],
        private array $excludedRoutes = [],
        private string $apiRoutePrefix = '/api/v2',
        private string $adminPathName = 'admin',
        private bool $denyUnresolvedOperations = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onKernelController', 5]];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isAdminApiRequest($request)) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (is_string($route) && in_array($route, $this->excludedRoutes, true)) {
            return;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        /**
         * Not an API Platform operation: a plain controller mounted under the admin API, such as
         * the statistics endpoint. Those carry no resource to derive a permission from, so they
         * are named in configuration exactly like the uncovered HTML routes.
         */
        if (!is_string($resourceClass) || !is_string($operationName)) {
            $this->denyUnlessDeclared($route);

            return;
        }

        $permission = $this->resolver->resolve($resourceClass, $operationName);

        if (null === $permission) {
            if ($this->denyUnresolvedOperations) {
                throw new AccessDeniedException(sprintf(
                    'Operation "%s" is under the admin API but no permission could be resolved for it.',
                    $operationName,
                ));
            }

            return;
        }

        if (!$this->authorizationChecker->isGranted($permission)) {
            throw new AccessDeniedException(sprintf(
                'Permission "%s" is required for operation "%s".',
                $permission,
                $operationName,
            ));
        }
    }

    private function denyUnlessDeclared(mixed $route): void
    {
        $declared = is_string($route) ? ($this->routePermissions[$route] ?? null) : null;

        if (null === $declared) {
            if ($this->denyUnresolvedOperations) {
                throw new AccessDeniedException(sprintf(
                    'Route "%s" is under the admin API but no permission covers it.',
                    is_string($route) ? $route : '(unnamed)',
                ));
            }

            return;
        }

        if (!$this->authorizationChecker->isGranted($declared)) {
            throw new AccessDeniedException(sprintf(
                'Permission "%s" is required for route "%s".',
                $declared,
                is_string($route) ? $route : '(unnamed)',
            ));
        }
    }

    private function isAdminApiRequest(Request $request): bool
    {
        return str_starts_with(
            $request->getPathInfo(),
            rtrim($this->apiRoutePrefix, '/') . '/' . $this->adminPathName . '/',
        );
    }
}
