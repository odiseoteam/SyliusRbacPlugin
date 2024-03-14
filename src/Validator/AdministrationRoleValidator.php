<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Validator;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AdministrationRoleValidator implements AdministrationRoleValidatorInterface
{
    public function __construct(
        private ValidatorInterface $validator
    ) {
    }

    public function validate(AdministrationRoleInterface $administrationRole, string $validationGroup): void
    {
        $constraintViolations = $this->validator->validate($administrationRole, null, $validationGroup);

        if ($constraintViolations->count() > 0) {
            /** @var string $message */
            $message = $constraintViolations->get(0)->getMessage();

            throw new \InvalidArgumentException($message);
        }
    }
}
