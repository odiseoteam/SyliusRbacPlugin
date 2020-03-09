<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Validator;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;

interface AdministrationRoleValidatorInterface
{
    public function validate(AdministrationRoleInterface $administrationRole, string $validationGroup): void;
}
