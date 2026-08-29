<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Form\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Built on `EntityType` rather than a plain `ChoiceType`.
 *
 * An administrator holds a Doctrine `Collection` of roles, and a plain choice field cannot read
 * one: it fails with "Expected an array". `EntityType` brings the collection-to-array
 * transformer that makes a multiple-selection field work against a Doctrine association.
 */
final class AdministrationRoleChoiceType extends AbstractType
{
    public function __construct(private readonly string $administrationRoleClass)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'class' => $this->administrationRoleClass,
            'choice_label' => 'name',
            'choice_value' => 'code',
            'label' => false,
        ]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'odiseo_rbac_administration_role_choice';
    }
}
