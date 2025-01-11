<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Form\Extension;

use Odiseo\SyliusRbacPlugin\Form\Type\AdministrationRoleChoiceType;
use Sylius\Bundle\CoreBundle\Form\Type\User\AdminUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

final class AdminUserTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('administrationRole', AdministrationRoleChoiceType::class, [
            'label' => 'odiseo_sylius_rbac_plugin.ui.rbac_role',
            'required' => true,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminUserType::class];
    }
}
