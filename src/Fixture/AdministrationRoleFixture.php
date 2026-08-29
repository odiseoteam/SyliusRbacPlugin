<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class AdministrationRoleFixture extends AbstractFixture implements FixtureInterface
{
    /** @param RepositoryInterface<LocaleInterface> $localeRepository */
    public function __construct(
        protected FactoryInterface $administrationRoleFactory,
        protected ObjectManager $administrationRoleManager,
        protected RepositoryInterface $localeRepository,
    ) {
    }

    public function load(array $options): void
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->administrationRoleFactory->createNew();

        /** @var string $code */
        $code = $options['code'];
        /** @var string $name */
        $name = $options['name'];

        $administrationRole->setCode($code);

        /**
         * The name is translated, and a translatable entity has no current locale until it is
         * told: writing the name without this fails with "No locale has been set". The same
         * name goes into every locale the shop has — a fixture has nothing better to offer, and
         * a missing translation renders blank.
         */
        foreach ($this->getLocaleCodes() as $localeCode) {
            $administrationRole->setCurrentLocale($localeCode);
            $administrationRole->setFallbackLocale($localeCode);

            $administrationRole->setName($name);
        }

        $this->administrationRoleManager->persist($administrationRole);
        $this->administrationRoleManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $node = $optionsNode->children();

        $node->scalarNode('code')->isRequired()->cannotBeEmpty();
        $node->scalarNode('name')->isRequired()->cannotBeEmpty();
    }

    public function getName(): string
    {
        return 'administration_role';
    }

    /** @return list<string> */
    private function getLocaleCodes(): array
    {
        return array_values(array_map(
            static fn (LocaleInterface $locale): string => (string) $locale->getCode(),
            $this->localeRepository->findAll(),
        ));
    }
}
