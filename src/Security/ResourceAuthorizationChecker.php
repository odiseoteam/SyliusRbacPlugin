<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security;

use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface as SymfonyAuthorizationChecker;

/**
 * Fills the authorization socket Sylius' resource controller leaves open.
 *
 * `ResourceController::isGrantedOr403()` asks this service, and the implementation shipped by
 * Sylius always answers true. Routing it to Symfony means the resource controller and every
 * other surface reach the same voter.
 *
 * It also substitutes the permission for workflow transitions. Sylius applies a transition
 * through `updateAction`, so it asks for `sylius.order.update` when cancelling an order — the
 * same permission as editing one. Asking for `sylius.order.cancel` instead is what makes
 * "may cancel orders" grantable without also granting "may edit orders".
 */
final readonly class ResourceAuthorizationChecker implements AuthorizationCheckerInterface
{
    public function __construct(private SymfonyAuthorizationChecker $authorizationChecker)
    {
    }

    public function isGranted(RequestConfiguration $configuration, string $permission): bool
    {
        return $this->authorizationChecker->isGranted($this->forTransition($configuration, $permission));
    }

    private function forTransition(RequestConfiguration $configuration, string $permission): string
    {
        if (!$configuration->hasStateMachine()) {
            return $permission;
        }

        $transition = $configuration->getStateMachineTransition();

        if (!is_string($transition)) {
            return $permission;
        }

        try {
            $identifier = PermissionIdentifier::fromString($permission);

            return PermissionIdentifier::of($identifier->package, $identifier->subject, $transition)->toString();
        } catch (\Throwable) {
            /**
             * An application using its own permission strings, or a transition name that is not
             * a valid segment. Falling back leaves the behaviour Sylius already had rather than
             * denying something over a naming detail.
             */
            return $permission;
        }
    }
}
