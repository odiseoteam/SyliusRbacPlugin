<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Webmozart\Assert\Assert;

/**
 * Assigns roles to admin users created by Sylius core's own `admin_user` fixture.
 *
 * A separate fixture rather than an override of `admin_user`'s `custom` list: that list is a
 * prototype node without a key attribute, so a second config source appends to it instead of
 * replacing it, which would recreate `sylius`/`api` and collide on their unique email/username.
 */
class AdminUserAdministrationRoleFixture extends AbstractFixture implements FixtureInterface
{
    /**
     * @param RepositoryInterface<AdminUserInterface> $adminUserRepository
     * @param RepositoryInterface<AdministrationRoleInterface> $administrationRoleRepository
     */
    public function __construct(
        protected RepositoryInterface $adminUserRepository,
        protected RepositoryInterface $administrationRoleRepository,
        protected ObjectManager $objectManager,
    ) {
    }

    public function load(array $options): void
    {
        /** @var string $roleCode */
        $roleCode = $options['role'];

        $administrationRole = $this->administrationRoleRepository->findOneBy(['code' => $roleCode]);
        Assert::isInstanceOf($administrationRole, AdministrationRoleInterface::class);

        /** @var list<string> $usernames */
        $usernames = $options['usernames'];

        foreach ($usernames as $username) {
            $adminUser = $this->adminUserRepository->findOneBy(['username' => $username]);

            /**
             * A username nothing created is skipped rather than fatal. This ships to every
             * installation, and the administrators it names are the ones Sylius' own demo
             * fixtures happen to create: an application that replaces them would otherwise get a
             * failing `sylius:fixtures:load` out of a convenience it never asked for.
             */
            if (!$adminUser instanceof AdministrationRoleAwareInterface) {
                continue;
            }

            $adminUser->addAdministrationRole($administrationRole);
        }

        $this->objectManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $node = $optionsNode->children();

        $node->scalarNode('role')->isRequired()->cannotBeEmpty();

        $usernames = $node->arrayNode('usernames');
        $usernames->isRequired();
        $usernames->scalarPrototype();
    }

    public function getName(): string
    {
        return 'admin_user_administration_role';
    }
}
