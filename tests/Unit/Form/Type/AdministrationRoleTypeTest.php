<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Form\Type;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Form\Type\AdministrationRoleType;
use Symfony\Component\Form\Test\TypeTestCase;

final class AdministrationRoleTypeTest extends TypeTestCase
{
    public function testItSubmitsTheName(): void
    {
        $administrationRole = new AdministrationRole();

        $form = $this->factory->create(
            AdministrationRoleType::class,
            $administrationRole,
            ['data_class' => AdministrationRole::class],
        );

        $form->submit(['name' => 'Catalog manager']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Catalog manager', $administrationRole->getName());
    }

    public function testItsBlockPrefixIsNamespaced(): void
    {
        $type = new AdministrationRoleType(AdministrationRole::class, ['odiseo']);

        $this->assertSame('odiseo_rbac_administration_role', $type->getBlockPrefix());
    }

    protected function getExtensions(): array
    {
        return [
            new \Symfony\Component\Form\PreloadedExtension(
                [new AdministrationRoleType(AdministrationRole::class, ['odiseo'])],
                [],
            ),
        ];
    }
}
