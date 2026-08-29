<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Grid;

use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use Sylius\Component\Grid\Definition\Action;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Resource\Metadata\RegistryInterface;
use Sylius\Resource\ResourceActions;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Disables the grid actions the administrator is not allowed to perform.
 *
 * A visible button that answers 403 is worse than an absent one: it reads as a broken
 * application rather than as a boundary. Actions are matched to the same permissions the
 * request will check, so the two cannot disagree.
 *
 * Two ways an action names what it does, and both are used:
 *
 * - the standard resource types (`create`, `update`, `delete`, `show`) are resolved through the
 *   grid's own model class, exactly as the resource controller resolves them;
 * - anything carrying a route — a `links` action, a custom button — is resolved through the
 *   route map. A `links` action keeps only the destinations that are allowed, and disappears
 *   when none of them is.
 */
final readonly class GridActionPermissionFilter
{
    private const ACTION_OPERATIONS = [
        'create' => ResourceActions::CREATE,
        'update' => ResourceActions::UPDATE,
        'delete' => ResourceActions::DELETE,
        'show' => ResourceActions::SHOW,
    ];

    private const BULK_GROUP = 'bulk';

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private RoutePermissionMapInterface $routePermissions,
        private RegistryInterface $resourceRegistry,
    ) {
    }

    public function filter(Grid $grid): void
    {
        $resourceClass = $grid->getDriverConfiguration()['class'] ?? null;

        foreach ($grid->getActionGroups() as $group) {
            foreach ($group->getActions() as $action) {
                if (!$action->isEnabled()) {
                    continue;
                }

                $this->filterAction($action, $group->getName(), is_string($resourceClass) ? $resourceClass : null);
            }
        }
    }

    private function filterAction(Action $action, string $groupName, ?string $resourceClass): void
    {
        $links = $this->filterLinks($action);

        if (null !== $links) {
            $action->setEnabled([] !== $links);

            return;
        }

        $permission = $this->permissionOf($action, $groupName, $resourceClass);

        if (null !== $permission && !$this->authorizationChecker->isGranted($permission)) {
            $action->setEnabled(false);
        }
    }

    /**
     * @return array<string, mixed>|null the surviving links, or null when this is not a links action
     */
    private function filterLinks(Action $action): ?array
    {
        $options = $action->getOptions();
        $links = $options['links'] ?? null;

        if (!is_array($links)) {
            return null;
        }

        $allowed = array_filter($links, function (mixed $link): bool {
            if (!is_array($link) || !is_string($link['route'] ?? null)) {
                return true;
            }

            $permission = $this->routePermissions->permissionFor($link['route']);

            return null === $permission || $this->authorizationChecker->isGranted($permission);
        });

        $options['links'] = $allowed;
        $action->setOptions($options);

        return $allowed;
    }

    private function permissionOf(Action $action, string $groupName, ?string $resourceClass): ?string
    {
        $link = $action->getOptions()['link'] ?? null;
        $route = is_array($link) ? ($link['route'] ?? null) : null;

        if (is_string($route)) {
            return $this->routePermissions->permissionFor($route);
        }

        $operation = self::ACTION_OPERATIONS[$action->getType()] ?? null;

        if (null === $operation || null === $resourceClass) {
            return null;
        }

        /**
         * The same button type means a different permission depending on where it sits: `delete`
         * in the bulk group deletes everything selected, which Sylius checks as `bulk_delete`.
         */
        if (self::BULK_GROUP === $groupName && ResourceActions::DELETE === $operation) {
            $operation = ResourceActions::BULK_DELETE;
        }

        try {
            return $this->resourceRegistry->getByClass($resourceClass)->getPermissionCode($operation);
        } catch (\Throwable) {
            return null;
        }
    }
}
