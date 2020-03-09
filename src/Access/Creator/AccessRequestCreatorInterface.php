<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Creator;

use Odiseo\SyliusRbacPlugin\Access\Exception\UnresolvedRouteNameException;
use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;

interface AccessRequestCreatorInterface
{
    /** @throws UnresolvedRouteNameException */
    public function createFromRouteName(string $routeName, string $requestMethod): AccessRequest;
}
