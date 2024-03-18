<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Creator;

use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;

interface AccessRequestCreatorInterface
{
    public function createFromRouteName(string $routeName, string $requestMethod): AccessRequest;
}
