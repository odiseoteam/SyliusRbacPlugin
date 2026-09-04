<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Factory;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AdminUserExampleFactory as BaseAdminUserExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserExampleFactory extends BaseAdminUserExampleFactory
{
    protected OptionsResolver $optionsResolver;

    /** @param RepositoryInterface<AdministrationRoleInterface> $administrationRoleRepository */
    public function __construct(
        protected RepositoryInterface $administrationRoleRepository,
        FactoryInterface $userFactory,
        string $localeCode,
        FileLocatorInterface $fileLocator,
        ImageUploaderInterface $imageUploader,
        FactoryInterface $avatarImageFactory,
    ) {
        $this->optionsResolver = new OptionsResolver();

        parent::__construct(
            $userFactory,
            $localeCode,
            $fileLocator,
            $imageUploader,
            $avatarImageFactory,
        );
    }

    public function create(array $options = []): AdminUserInterface
    {
        $user = parent::create($options);

        $this->configureOptions($this->optionsResolver);

        $options = $this->optionsResolver->resolve($options);

        if (!isset($options['administration_roles']) || !$user instanceof AdministrationRoleAwareInterface) {
            return $user;
        }

        /** @var iterable<AdministrationRoleInterface> $administrationRoles */
        $administrationRoles = $options['administration_roles'];

        foreach ($administrationRoles as $administrationRole) {
            $user->addAdministrationRole($administrationRole);
        }

        return $user;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        /**
         * Looked up by `code`, not by name: the name is translated now, so it is neither unique
         * nor stable enough for a fixture to point at.
         */
        $resolver
            ->setDefined('administration_roles')
            ->setAllowedTypes('administration_roles', ['array'])
            ->setNormalizer('administration_roles', LazyOption::findBy($this->administrationRoleRepository, 'code'))
        ;
    }
}
