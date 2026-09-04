<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Exception;

use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;

/**
 * Thrown when something asks the registry for a permission nobody declared.
 *
 * Deliberately an exception rather than a denial. A permission that does not exist is a bug in
 * whoever asked — a typo, a stale reference, a route nobody covered — and denying quietly would
 * bury it.
 */
final class UnknownPermissionException extends \InvalidArgumentException
{
    public function __construct(PermissionIdentifier $identifier)
    {
        parent::__construct(sprintf(
            'Permission "%s" is not registered. Declare it with #[RbacPermission] or check for a typo; run "odiseo:rbac:debug" to list what exists.',
            $identifier->toString(),
        ));
    }
}
