<?php

declare(strict_types=1);

namespace spec\Odiseo\SyliusRbacPlugin\Validator;

use PhpSpec\ObjectBehavior;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Validator\AdministrationRoleValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AdministrationRoleValidatorSpec extends ObjectBehavior
{
    function let(ValidatorInterface $validator): void
    {
        $this->beConstructedWith($validator);
    }

    function it_implements_administration_role_validator_interface(): void
    {
        $this->shouldImplement(AdministrationRoleValidatorInterface::class);
    }

    function it_validates_administration_role_entity_against_constraints(
        AdministrationRoleInterface $administrationRole,
        ValidatorInterface $validator,
        ConstraintViolationListInterface $constraintViolationList
    ): void {
        $validator
            ->validate($administrationRole, null, 'odiseo_sylius_rbac_plugin_admin_administration_role_create')
            ->willReturn($constraintViolationList)
        ;

        $constraintViolationList->count()->willReturn(0);

        $this->validate($administrationRole, 'odiseo_sylius_rbac_plugin_admin_administration_role_create');
    }

    function it_throws_exception_when_constraint_violation_occurs(
        AdministrationRoleInterface $administrationRole,
        ValidatorInterface $validator,
        ConstraintViolationListInterface $constraintViolationList,
        ConstraintViolationInterface $constraintViolation
    ): void {
        $validator
            ->validate($administrationRole, null, 'odiseo_sylius_rbac_plugin_admin_administration_role_create')
            ->willReturn($constraintViolationList)
        ;

        $constraintViolationList->count()->willReturn(1);
        $constraintViolationList->get(0)->willReturn($constraintViolation);

        $constraintViolation->getMessage()->willReturn('This name is already taken');

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('validate', [$administrationRole, 'odiseo_sylius_rbac_plugin_admin_administration_role_create'])
        ;
    }
}
