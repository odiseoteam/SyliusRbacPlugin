<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Sylius\Resource\Metadata\RegistryInterface;
use Sylius\Resource\ResourceActions;

/**
 * Names the permission an admin API operation requires.
 *
 * The identifier comes from `Metadata::getPermissionCode()`, the same method the resource
 * controller uses, so `sylius.product.update` means the same thing whether it is asked for by a
 * form submission or by a PUT on the API. A role does not have to be granted twice.
 */
final readonly class ApiOperationPermissionResolver implements ApiOperationPermissionResolverInterface
{
    private const METHOD_OPERATIONS = [
        'POST' => ResourceActions::CREATE,
        'PUT' => ResourceActions::UPDATE,
        'PATCH' => ResourceActions::UPDATE,
        'DELETE' => ResourceActions::DELETE,
    ];

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $metadataFactory,
        private RegistryInterface $resourceRegistry,
    ) {
    }

    public function resolve(string $resourceClass, string $operationName): ?string
    {
        try {
            $operation = $this->metadataFactory->create($resourceClass)->getOperation($operationName);
            $metadata = $this->resourceRegistry->getByClass($resourceClass);
        } catch (\Throwable) {
            return null;
        }

        if (!$operation instanceof HttpOperation) {
            return null;
        }

        $method = strtoupper($operation->getMethod());

        if ('GET' === $method || 'HEAD' === $method) {
            return $metadata->getPermissionCode(
                $operation instanceof CollectionOperationInterface ? ResourceActions::INDEX : ResourceActions::SHOW,
            );
        }

        $action = self::METHOD_OPERATIONS[$method] ?? null;

        return null === $action ? null : $metadata->getPermissionCode($action);
    }
}
