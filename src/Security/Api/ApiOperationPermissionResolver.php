<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Security\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Sylius\Resource\Metadata\MetadataInterface;
use Sylius\Resource\Metadata\RegistryInterface;
use Sylius\Resource\ResourceActions;

/**
 * Names the permission an admin API operation requires.
 *
 * The identifier comes from `Metadata::getPermissionCode()`, the same method the resource
 * controller uses, so `sylius.product.update` means the same thing whether it is asked for by a
 * form submission or by a PUT on the API. A role does not have to be granted twice.
 *
 * Named actions are the exception the HTTP method cannot express. `PATCH /shipments/{id}/ship`
 * and `PATCH /shipments/{id}` are both an update as far as the method goes, but the admin
 * screens ask for `sylius.shipment.ship` and `sylius.shipment.update` respectively -- see
 * `ResourceAuthorizationChecker`, which derives the same distinction from the state machine
 * transition. Without this the two surfaces disagree, and an administrator denied "may ship"
 * ships anyway through the API.
 *
 * `foldedSubjects` catches a different case: a resource the API models with a full CRUD of its
 * own, but that the admin never asks a permission of independently of its parent -- a product's
 * images, a country's provinces, a promotion's rules. Those resolve to the parent's identifier
 * instead of their own; see `Configuration::FOLDED_API_SUBJECTS` for why.
 */
final class ApiOperationPermissionResolver implements ApiOperationPermissionResolverInterface
{
    private const METHOD_OPERATIONS = [
        'POST' => ResourceActions::CREATE,
        'PUT' => ResourceActions::UPDATE,
        'PATCH' => ResourceActions::UPDATE,
        'DELETE' => ResourceActions::DELETE,
    ];

    private const READ_ACTIONS = [ResourceActions::INDEX, ResourceActions::SHOW];

    /** @var array<string, string|null> */
    private array $itemUriTemplates = [];

    /** @param array<string, string> $foldedSubjects "{package}.{subject}" => the parent it folds into */
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $metadataFactory,
        private readonly RegistryInterface $resourceRegistry,
        private readonly array $foldedSubjects = [],
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

        $action = $this->namedActionOf($resourceClass, $operation) ?? $this->plainActionOf($operation);

        if (null === $action) {
            return null;
        }

        $parent = $this->foldedSubjects[$this->subjectKeyOf($metadata)] ?? null;

        if (null !== $parent) {
            return $parent . '.' . (in_array($action, self::READ_ACTIONS, true) ? ResourceActions::SHOW : ResourceActions::UPDATE);
        }

        return $metadata->getPermissionCode($action);
    }

    private function subjectKeyOf(MetadataInterface $metadata): string
    {
        return $metadata->getApplicationName() . '.' . $metadata->getName();
    }

    /**
     * The action of an operation hanging off a single record: everything after the item's own
     * URI, when that is one static segment. `/admin/shipments/{id}/ship` yields `ship`.
     *
     * Read from the URI rather than from the message or processor behind it, because the URI is
     * what Sylius names the action in and is the same string the HTML route uses for its
     * transition. A sub-resource collection such as `/admin/products/{code}/images` also ends in
     * a static segment, so it is ruled out by its own metadata rather than by pattern-matching
     * the word.
     */
    private function namedActionOf(string $resourceClass, HttpOperation $operation): ?string
    {
        $itemUriTemplate = $this->itemUriTemplateOf($resourceClass);
        $uriTemplate = (string) $operation->getUriTemplate();

        if (null === $itemUriTemplate || !str_starts_with($uriTemplate, $itemUriTemplate . '/')) {
            return null;
        }

        $action = str_replace('-', '_', substr($uriTemplate, strlen($itemUriTemplate) + 1));

        return PermissionIdentifier::isValidSegment($action) ? $action : null;
    }

    private function plainActionOf(HttpOperation $operation): ?string
    {
        $method = strtoupper($operation->getMethod());

        if ('GET' === $method || 'HEAD' === $method) {
            return $operation instanceof CollectionOperationInterface ? ResourceActions::INDEX : ResourceActions::SHOW;
        }

        return self::METHOD_OPERATIONS[$method] ?? null;
    }

    /** The URI of a single record of this resource, which every named action extends. */
    private function itemUriTemplateOf(string $resourceClass): ?string
    {
        if (array_key_exists($resourceClass, $this->itemUriTemplates)) {
            return $this->itemUriTemplates[$resourceClass];
        }

        $this->itemUriTemplates[$resourceClass] = null;

        try {
            $resources = $this->metadataFactory->create($resourceClass);
        } catch (\Throwable) {
            return null;
        }

        foreach ($resources as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if (!$operation instanceof HttpOperation || $operation instanceof CollectionOperationInterface) {
                    continue;
                }

                if ('GET' === strtoupper($operation->getMethod())) {
                    return $this->itemUriTemplates[$resourceClass] = (string) $operation->getUriTemplate();
                }
            }
        }

        return null;
    }
}
