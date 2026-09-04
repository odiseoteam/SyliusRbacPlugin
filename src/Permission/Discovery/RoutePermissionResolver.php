<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Sylius\Resource\ResourceActions;
use Symfony\Component\Routing\Route;

/**
 * Turns a single admin route into the permission identifier Sylius checks for it.
 *
 * Shared with the data migration so both derive identifiers by the same rule. Two
 * implementations would drift, and one of them rewrites permissions in production databases.
 */
final readonly class RoutePermissionResolver
{
    private const CONTROLLER_SEPARATOR = '::';

    private const SERVICE_INFIX = '.controller.';

    private const SEGMENT_FORMAT = '/^[a-z][a-z0-9_]*$/';

    /**
     * Which permission each controller action actually asks for.
     *
     * The last three are not guesses: `applyStateMachineTransitionAction`,
     * `updatePositionsAction` and `updateProductTaxonsPositionsAction` all call
     * `isGrantedOr403($configuration, ResourceActions::UPDATE)` in Sylius' own controllers,
     * which is why Sylius on its own cannot tell "cancel this order" from "edit this order".
     * Transition routes are given their own operation below rather than falling in here.
     */
    private const ACTION_OPERATIONS = [
        'indexAction' => ResourceActions::INDEX,
        'showAction' => ResourceActions::SHOW,
        'createAction' => ResourceActions::CREATE,
        'updateAction' => ResourceActions::UPDATE,
        'deleteAction' => ResourceActions::DELETE,
        'bulkDeleteAction' => ResourceActions::BULK_DELETE,
        'applyStateMachineTransitionAction' => ResourceActions::UPDATE,
        'updatePositionsAction' => ResourceActions::UPDATE,
        'updateProductTaxonsPositionsAction' => ResourceActions::UPDATE,
    ];

    /**
     * A workflow transition asks for a permission of its own: `sylius.order.cancel` rather than
     * `sylius.order.update`.
     *
     * Cancelling an order, refunding a payment and marking a shipment as shipped are the
     * operations a shop most wants to hand out separately, and inheriting Sylius' checks makes
     * all three indistinguishable from editing the record.
     *
     * A transition whose name is not a valid segment falls back to the plain action, so an
     * unusual workflow degrades to the old behaviour instead of breaking the registry.
     */
    public function transitionOf(Route $route): ?string
    {
        $options = $route->getDefault('_sylius');

        if (!is_array($options) || !is_array($options['state_machine'] ?? null)) {
            return null;
        }

        $transition = $options['state_machine']['transition'] ?? null;

        if (!is_string($transition) || 1 !== preg_match(self::SEGMENT_FORMAT, $transition)) {
            return null;
        }

        return $transition;
    }

    public function enforcesPermission(Route $route): bool
    {
        $options = $route->getDefault('_sylius');

        return is_array($options) && true === ($options['permission'] ?? false);
    }

    /** @throws UnmappableRouteException */
    public function resolve(Route $route): PermissionIdentifier
    {
        $controller = $route->getDefault('_controller');

        if (!is_string($controller) || !str_contains($controller, self::CONTROLLER_SEPARATOR)) {
            throw UnmappableRouteException::notAServiceAction(is_string($controller) ? $controller : '');
        }

        [$service, $action] = explode(self::CONTROLLER_SEPARATOR, $controller, 2);
        $action = rtrim($action, '()');

        $operation = $this->transitionOf($route)
            ?? self::ACTION_OPERATIONS[$action]
            ?? throw UnmappableRouteException::unknownAction($action);

        $position = strpos($service, self::SERVICE_INFIX);

        if (false === $position) {
            throw UnmappableRouteException::notAResourceController($service);
        }

        try {
            return PermissionIdentifier::of(
                substr($service, 0, $position),
                substr($service, $position + strlen(self::SERVICE_INFIX)),
                $operation,
            );
        } catch (InvalidPermissionSyntaxException $exception) {
            throw UnmappableRouteException::malformedIdentifier($service, $exception);
        }
    }
}
