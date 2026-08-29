<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\FormBuilderInterface;

final class AdministrationRoleType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => AdministrationRoleTranslationType::class,
                'label' => false,
            ])
            /**
             * Adds the `code` field and disables it once the role has one. The code is the
             * identity fixtures and the anti-lockout guard refer to, so letting it change would
             * silently break those references instead of failing loudly.
             */
            ->addEventSubscriber(new AddCodeFormSubscriber())
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'odiseo_rbac_administration_role';
    }
}
