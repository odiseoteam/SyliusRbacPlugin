<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Factory;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AdminUserExampleFactory as BaseAdminUserExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserExampleFactory extends BaseAdminUserExampleFactory
{
    protected OptionsResolver $optionsResolver;

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

        if (!isset($options['administration_role'])) {
            return $user;
        }

        if ($user instanceof AdministrationRoleAwareInterface) {
            $user->setAdministrationRole($options['administration_role']);
        }

        return $user;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefined('administration_role')
            ->setAllowedTypes('administration_role', ['string', AdministrationRoleInterface::class, 'null'])
            ->setNormalizer('administration_role', LazyOption::findOneBy($this->administrationRoleRepository, 'name'))
        ;
    }
}
