<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Factory;

use Sylius\Component\Resource\Factory\TranslatableFactoryInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;

interface AdministrationRoleFactoryInterface extends TranslatableFactoryInterface
{
    public function createWithNameAndPermissions(string $name, array $permissions): AdministrationRoleInterface;

    public function createWithName(string $name): AdministrationRoleInterface;
}
