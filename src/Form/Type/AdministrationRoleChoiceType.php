<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Form\Type;

use Doctrine\Persistence\ObjectRepository;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AdministrationRoleChoiceType extends AbstractType
{
    /** @param ObjectRepository<AdministrationRoleInterface> $administrationRoleRepository */
    public function __construct(
        private ObjectRepository $administrationRoleRepository,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'choices' => $this->administrationRoleRepository->findAll(),
            'choice_value' => 'id',
            'choice_label' => 'name',
            'label' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'odiseo_sylius_rbac_administration_role_choice';
    }
}
