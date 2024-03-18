<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Factory;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Component\Resource\Factory\TranslatableFactoryInterface;

interface AdministrationRoleFactoryInterface extends TranslatableFactoryInterface
{
    public function createWithNameAndPermissions(string $name, array $permissions): AdministrationRoleInterface;

    public function createWithName(string $name): AdministrationRoleInterface;
}
