<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Odiseo\SyliusRbacPlugin\Security\Api\ApiOperationPermissionResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Metadata\MetadataInterface;
use Sylius\Resource\Metadata\RegistryInterface;

final class ApiOperationPermissionResolverTest extends TestCase
{
    public function testACollectionGetIsAnIndex(): void
    {
        self::assertSame('sylius.shipment.index', $this->resolve(new GetCollection(uriTemplate: '/admin/shipments')));
    }

    public function testAnItemGetIsAShow(): void
    {
        self::assertSame('sylius.shipment.show', $this->resolve(new Get(uriTemplate: '/admin/shipments/{id}')));
    }

    public function testAPatchOnTheRecordItselfIsAnUpdate(): void
    {
        self::assertSame('sylius.shipment.update', $this->resolve(new Patch(uriTemplate: '/admin/shipments/{id}')));
    }

    public function testADeleteIsADelete(): void
    {
        self::assertSame('sylius.shipment.delete', $this->resolve(new Delete(uriTemplate: '/admin/shipments/{id}')));
    }

    /**
     * The distinction the HTTP method cannot make. Sylius applies a transition through a PATCH on
     * a sub-path, so without this "may mark a shipment shipped" and "may edit a shipment" are the
     * same permission on the API while the admin screens treat them as two -- and the wider of
     * the two wins.
     */
    public function testAnActionHangingOffTheRecordGetsItsOwnOperation(): void
    {
        self::assertSame('sylius.shipment.ship', $this->resolve(new Patch(uriTemplate: '/admin/shipments/{id}/ship')));
    }

    public function testAHyphenatedActionBecomesOneSegment(): void
    {
        self::assertSame(
            'sylius.shipment.resend_confirmation_email',
            $this->resolve(new Post(uriTemplate: '/admin/shipments/{id}/resend-confirmation-email')),
        );
    }

    /**
     * A path deeper than one segment is not an action of this record -- it addresses something
     * else -- so it falls back to what the method says rather than inventing a name.
     */
    public function testAPathDeeperThanOneSegmentIsNotTreatedAsAnAction(): void
    {
        self::assertSame(
            'sylius.shipment.update',
            $this->resolve(new Patch(uriTemplate: '/admin/shipments/{id}/units/{unitId}')),
        );
    }

    public function testAnOperationOnAResourceWithNoItemUriFallsBackToTheMethod(): void
    {
        self::assertSame(
            'sylius.shipment.create',
            $this->resolve(new Post(uriTemplate: '/admin/shipments/{id}/ship'), itemUriTemplate: null),
        );
    }

    /**
     * The case `FOLDED_API_SUBJECTS` exists for: a resource the API gives a full CRUD of its
     * own, but that the admin never asks a permission of independently of its parent -- a
     * shipment's own image field, say. Every mutation folds to the parent's `update`.
     */
    public function testACreateOnAFoldedSubjectRequiresTheParentsUpdate(): void
    {
        self::assertSame(
            'sylius.order.update',
            $this->resolve(new Post(uriTemplate: '/admin/shipments'), foldedSubjects: ['sylius.shipment' => 'sylius.order']),
        );
    }

    public function testADeleteOnAFoldedSubjectRequiresTheParentsUpdate(): void
    {
        self::assertSame(
            'sylius.order.update',
            $this->resolve(new Delete(uriTemplate: '/admin/shipments/{id}'), foldedSubjects: ['sylius.shipment' => 'sylius.order']),
        );
    }

    /** A read on a folded subject requires only the parent's `show`, not its `update`. */
    public function testAReadOnAFoldedSubjectRequiresOnlyTheParentsShow(): void
    {
        self::assertSame(
            'sylius.order.show',
            $this->resolve(new GetCollection(uriTemplate: '/admin/shipments'), foldedSubjects: ['sylius.shipment' => 'sylius.order']),
        );
    }

    /** A named action is a mutation as far as folding goes: there is no gentler way to "ship". */
    public function testANamedActionOnAFoldedSubjectRequiresTheParentsUpdate(): void
    {
        self::assertSame(
            'sylius.order.update',
            $this->resolve(new Patch(uriTemplate: '/admin/shipments/{id}/ship'), foldedSubjects: ['sylius.shipment' => 'sylius.order']),
        );
    }

    public function testAnUnfoldedSubjectIsUnaffectedByTheFoldedList(): void
    {
        self::assertSame(
            'sylius.shipment.update',
            $this->resolve(new Patch(uriTemplate: '/admin/shipments/{id}'), foldedSubjects: ['sylius.province' => 'sylius.country']),
        );
    }

    public function testAnUnknownResourceResolvesToNothing(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory->method('create')->willThrowException(new \RuntimeException());

        $resolver = new ApiOperationPermissionResolver($factory, $this->createMock(RegistryInterface::class));

        self::assertNull($resolver->resolve('An\\Unknown\\Resource', 'an_operation'));
    }

    private function resolve(
        HttpOperation $operation,
        ?string $itemUriTemplate = '/admin/shipments/{id}',
        array $foldedSubjects = [],
    ): ?string {
        $resourceClass = 'App\\Shipment';
        $operation = $operation->withName('the_operation')->withClass($resourceClass);

        $operations = [$operation];

        if (null !== $itemUriTemplate) {
            $operations[] = (new Get(uriTemplate: $itemUriTemplate))->withName('item_get')->withClass($resourceClass);
        }

        $collection = new ResourceMetadataCollection($resourceClass, [
            (new ApiResource())->withOperations(new Operations(array_combine(
                array_map(static fn (HttpOperation $o): string => (string) $o->getName(), $operations),
                $operations,
            ))),
        ]);

        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory->method('create')->with($resourceClass)->willReturn($collection);

        $metadata = $this->createMock(MetadataInterface::class);
        $metadata->method('getApplicationName')->willReturn('sylius');
        $metadata->method('getName')->willReturn('shipment');
        $metadata->method('getPermissionCode')->willReturnCallback(
            static fn (string $action): string => 'sylius.shipment.' . $action,
        );

        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getByClass')->with($resourceClass)->willReturn($metadata);

        return (new ApiOperationPermissionResolver($factory, $registry, $foldedSubjects))->resolve($resourceClass, 'the_operation');
    }
}
