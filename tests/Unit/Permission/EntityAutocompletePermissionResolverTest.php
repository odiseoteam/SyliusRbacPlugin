<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\EntityAutocompletePermissionResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Metadata\MetadataInterface;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;

final class EntityAutocompletePermissionResolverTest extends TestCase
{
    public function testItResolvesAnAliasFixedToOneEntityWithoutTouchingTheRequest(): void
    {
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects(self::never())->method('getByClass');

        $resolver = new EntityAutocompletePermissionResolver($registry, ['sylius_admin_taxon' => 'sylius.taxon.index']);

        self::assertSame(
            'sylius.taxon.index',
            $resolver->resolve('sylius_admin_taxon', Request::create('/admin/autocomplete/sylius_admin_taxon')),
        );
    }

    public function testItResolvesAGridFilterFromTheSignedExtraOptions(): void
    {
        $metadata = $this->createMock(MetadataInterface::class);
        $metadata->method('getPermissionCode')->with('index')->willReturn('sylius.product.index');

        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getByClass')->with('App\Entity\Product')->willReturn($metadata);

        $resolver = new EntityAutocompletePermissionResolver($registry);

        $request = Request::create('/admin/autocomplete/sylius_admin_grid_filter_autocomplete', parameters: [
            'extra_options' => self::encode(['class' => 'App\Entity\Product']),
        ]);

        self::assertSame('sylius.product.index', $resolver->resolve('sylius_admin_grid_filter_autocomplete', $request));
    }

    public function testItGivesUpWhenTheRegistryDoesNotKnowTheClass(): void
    {
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getByClass')->willThrowException(new \InvalidArgumentException());

        $resolver = new EntityAutocompletePermissionResolver($registry);

        $request = Request::create('/admin/autocomplete/sylius_admin_grid_filter_autocomplete', parameters: [
            'extra_options' => self::encode(['class' => 'App\Entity\Unregistered']),
        ]);

        self::assertNull($resolver->resolve('sylius_admin_grid_filter_autocomplete', $request));
    }

    public function testItGivesUpWhenExtraOptionsIsMissing(): void
    {
        $resolver = new EntityAutocompletePermissionResolver($this->createMock(RegistryInterface::class));

        self::assertNull($resolver->resolve(
            'sylius_admin_grid_filter_autocomplete',
            Request::create('/admin/autocomplete/sylius_admin_grid_filter_autocomplete'),
        ));
    }

    public function testItGivesUpWhenExtraOptionsIsNotValidBase64Json(): void
    {
        $resolver = new EntityAutocompletePermissionResolver($this->createMock(RegistryInterface::class));

        $request = Request::create('/admin/autocomplete/sylius_admin_grid_filter_autocomplete', parameters: [
            'extra_options' => 'not valid at all',
        ]);

        self::assertNull($resolver->resolve('sylius_admin_grid_filter_autocomplete', $request));
    }

    /** @param array<string, mixed> $data */
    private static function encode(array $data): string
    {
        return base64_encode(json_encode($data, \JSON_THROW_ON_ERROR));
    }
}
