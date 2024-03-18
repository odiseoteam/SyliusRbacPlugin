<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Access\Creator;

use Odiseo\SyliusRbacPlugin\Access\Exception\UnresolvedRouteNameException;
use Odiseo\SyliusRbacPlugin\Access\Model\AccessRequest;
use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Odiseo\SyliusRbacPlugin\Access\Model\Section;

final class AccessRequestCreator implements AccessRequestCreatorInterface
{
    public function __construct(
        private array $configuration,
    ) {
    }

    public function createFromRouteName(string $routeName, string $requestMethod): AccessRequest
    {
        $operationType = $this->resolveOperationType($requestMethod);

        foreach ($this->configuration['configuration'] as $configurationRoutePrefix) {
            if (str_starts_with($routeName, $configurationRoutePrefix)) {
                return new AccessRequest(Section::configuration(), $operationType);
            }
        }

        foreach ($this->configuration['customers_management'] as $customersRoutePrefix) {
            if (str_starts_with($routeName, $customersRoutePrefix)) {
                return new AccessRequest(Section::customers(), $operationType);
            }
        }

        foreach ($this->configuration['marketing_management'] as $marketingRoutePrefix) {
            if (str_starts_with($routeName, $marketingRoutePrefix)) {
                return new AccessRequest(Section::marketing(), $operationType);
            }
        }

        foreach ($this->configuration['sales_management'] as $salesRoutePrefix) {
            if (str_starts_with($routeName, $salesRoutePrefix)) {
                return new AccessRequest(Section::sales(), $operationType);
            }
        }

        foreach ($this->configuration['catalog_management'] as $catalogRoutePrefix) {
            if (str_starts_with($routeName, $catalogRoutePrefix)) {
                return new AccessRequest(Section::catalog(), $operationType);
            }
        }

        foreach ($this->configuration['custom'] as $sectionName => $sectionPrefixes) {
            foreach ($sectionPrefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return new AccessRequest(Section::ofType($sectionName), $operationType);
                }
            }
        }

        throw UnresolvedRouteNameException::withRouteName($routeName);
    }

    public function resolveOperationType(string $requestMethod): OperationType
    {
        if ('GET' === $requestMethod || 'HEAD' === $requestMethod) {
            return OperationType::read();
        }

        return OperationType::write();
    }
}
