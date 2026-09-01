<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Http;

use Odiseo\SyliusRbacPlugin\Permission\EntityAutocompletePermissionResolverInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces permissions on the admin routes the resource controller does not check itself.
 *
 * Three kinds of route reach the admin, and only one is already handled:
 *
 * - a resource route carrying `_sylius: permission: true` is checked by `ResourceController`,
 *   so this listener leaves it alone;
 * - a route named in `odiseo_sylius_rbac.route_permissions` is checked here, because nothing
 *   else ever looks at those declarations;
 * - anything else is unprotected. It is denied when `deny_unprotected_admin_routes` is on,
 *   which is the default, and `excluded_routes` is the list of deliberate exceptions.
 *
 * `sylius_admin_entity_autocomplete` is a fourth kind of its own: one route shared by every
 * autocomplete field, so no single declared permission means the right thing for all of it. Its
 * permission is resolved per request instead, by `EntityAutocompletePermissionResolver`.
 */
final readonly class AdminRouteAuthorizationListener implements EventSubscriberInterface
{
    /**
     * @param array<string, string> $routePermissions route name => permission identifier
     * @param list<string> $excludedRoutes routes that deliberately require no permission
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $routePermissions,
        private array $excludedRoutes,
        private EntityAutocompletePermissionResolverInterface $entityAutocompleteResolver,
        private bool $denyUnprotectedRoutes = true,
        private string $adminPathName = 'admin',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!is_string($route) || !$this->isAdminPath($request->getPathInfo())) {
            return;
        }

        if (in_array($route, $this->excludedRoutes, true)) {
            return;
        }

        if (EntityAutocompletePermissionResolverInterface::ROUTE === $route) {
            $this->enforceEntityAutocomplete($request);

            return;
        }

        $declared = $this->routePermissions[$route] ?? null;

        if (null !== $declared) {
            if (!$this->authorizationChecker->isGranted($declared)) {
                throw new AccessDeniedException(sprintf('Permission "%s" is required for route "%s".', $declared, $route));
            }

            return;
        }

        // The resource controller checks these itself, with the identifier its metadata builds.
        if ($this->enforcesPermissionItself($request->attributes->get('_sylius'))) {
            return;
        }

        if ($this->denyUnprotectedRoutes) {
            throw new AccessDeniedException(sprintf(
                'Route "%s" is under the admin but no permission covers it. Declare one under ' .
                '"odiseo_sylius_rbac.route_permissions", list it under "excluded_routes" if it is ' .
                'meant to be open, or turn off "deny_unprotected_admin_routes".',
                $route,
            ));
        }
    }

    private function enforceEntityAutocomplete(Request $request): void
    {
        $alias = $request->attributes->get('alias');
        $permission = is_string($alias) ? $this->entityAutocompleteResolver->resolve($alias, $request) : null;

        if (null === $permission) {
            throw new AccessDeniedException(sprintf(
                'No permission could be resolved for entity-autocomplete alias "%s".',
                is_string($alias) ? $alias : '(unknown)',
            ));
        }

        if (!$this->authorizationChecker->isGranted($permission)) {
            throw new AccessDeniedException(sprintf(
                'Permission "%s" is required for entity-autocomplete alias "%s".',
                $permission,
                is_string($alias) ? $alias : '(unknown)',
            ));
        }
    }

    private function isAdminPath(string $path): bool
    {
        return str_starts_with($path, '/' . $this->adminPathName);
    }

    private function enforcesPermissionItself(mixed $syliusOptions): bool
    {
        return is_array($syliusOptions) && false !== ($syliusOptions['permission'] ?? false);
    }
}
