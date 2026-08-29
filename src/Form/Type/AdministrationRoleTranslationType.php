<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class AdministrationRoleTranslationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('name', TextType::class, [
                'label' => 'sylius.ui.name',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'odiseo_rbac_administration_role_translation';
    }
}
