<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Form\Type;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleTranslation;
use Odiseo\SyliusRbacPlugin\Form\Type\AdministrationRoleTranslationType;
use Odiseo\SyliusRbacPlugin\Form\Type\AdministrationRoleType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Sylius\Component\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class AdministrationRoleTypeTest extends TypeTestCase
{
    private const LOCALE = 'en_US';

    public function testItSubmitsTheCodeAndTheTranslatedName(): void
    {
        $administrationRole = new AdministrationRole();
        $administrationRole->setCurrentLocale(self::LOCALE);
        $administrationRole->setFallbackLocale(self::LOCALE);

        $form = $this->factory->create(
            AdministrationRoleType::class,
            $administrationRole,
            ['data_class' => AdministrationRole::class],
        );

        $form->submit([
            'code' => 'catalog_manager',
            'translations' => [self::LOCALE => ['name' => 'Catalog manager']],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('catalog_manager', $administrationRole->getCode());
        self::assertSame('Catalog manager', $administrationRole->getName());
    }

    public function testItLeavesTheCodeEditableWhileTheRoleHasNone(): void
    {
        $administrationRole = new AdministrationRole();
        $administrationRole->setCurrentLocale(self::LOCALE);
        $administrationRole->setFallbackLocale(self::LOCALE);

        $form = $this->factory->create(
            AdministrationRoleType::class,
            $administrationRole,
            ['data_class' => AdministrationRole::class],
        );

        self::assertFalse($form->get('code')->isDisabled());
    }

    public function testItDisablesTheCodeOnceTheRoleHasOne(): void
    {
        $administrationRole = new AdministrationRole();
        $administrationRole->setCurrentLocale(self::LOCALE);
        $administrationRole->setFallbackLocale(self::LOCALE);
        $administrationRole->setCode('catalog_manager');

        $form = $this->factory->create(
            AdministrationRoleType::class,
            $administrationRole,
            ['data_class' => AdministrationRole::class],
        );

        self::assertTrue($form->get('code')->isDisabled());

        $form->submit([
            'code' => 'something_else',
            'translations' => [self::LOCALE => ['name' => 'Catalog manager']],
        ]);

        self::assertSame('catalog_manager', $administrationRole->getCode());
    }

    public function testItsBlockPrefixIsNamespaced(): void
    {
        $type = new AdministrationRoleType(AdministrationRole::class, ['odiseo']);

        self::assertSame('odiseo_rbac_administration_role', $type->getBlockPrefix());
    }

    protected function getExtensions(): array
    {
        $localeProvider = $this->createMock(TranslationLocaleProviderInterface::class);
        $localeProvider->method('getDefinedLocalesCodes')->willReturn([self::LOCALE]);
        $localeProvider->method('getDefaultLocaleCode')->willReturn(self::LOCALE);

        return [
            new PreloadedExtension(
                [
                    new AdministrationRoleType(AdministrationRole::class, ['odiseo']),
                    new AdministrationRoleTranslationType(AdministrationRoleTranslation::class, ['odiseo']),
                    new ResourceTranslationsType($localeProvider),
                ],
                [],
            ),
        ];
    }
}
