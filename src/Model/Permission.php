<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Model;

use Odiseo\SyliusRbacPlugin\Access\Model\OperationType;
use Webmozart\Assert\Assert;

final class Permission implements PermissionInterface
{
    public const CATALOG_MANAGEMENT_PERMISSION = 'catalog_management';
    public const CONFIGURATION_PERMISSION = 'configuration';
    public const CUSTOMERS_MANAGEMENT_PERMISSION = 'customers_management';
    public const MARKETING_MANAGEMENT_PERMISSION = 'marketing_management';
    public const SALES_MANAGEMENT_PERMISSION = 'sales_management';

    private string $type;

    private array $operationTypes;

    public static function catalogManagement(array $operationTypes = []): self
    {
        return new self(self::CATALOG_MANAGEMENT_PERMISSION, $operationTypes);
    }

    public static function configuration(array $operationTypes = []): self
    {
        return new self(self::CONFIGURATION_PERMISSION, $operationTypes);
    }

    public static function customerManagement(array $operationTypes = []): self
    {
        return new self(self::CUSTOMERS_MANAGEMENT_PERMISSION, $operationTypes);
    }

    public static function marketingManagement(array $operationTypes = []): self
    {
        return new self(self::MARKETING_MANAGEMENT_PERMISSION, $operationTypes);
    }

    public static function salesManagement(array $operationTypes = []): self
    {
        return new self(self::SALES_MANAGEMENT_PERMISSION, $operationTypes);
    }

    public static function ofType(string $type, array $operationTypes = []): self
    {
        return new self($type, $operationTypes);
    }

    public function serialize(): string
    {
        /** @var string $serializedPermission */
        $serializedPermission = json_encode([
            'type' => $this->type(),
            'operation_types' => array_map(function (OperationType $operationType): string {
                return $operationType->__toString();
            }, $this->operationTypes()),
        ]);

        return $serializedPermission;
    }

    public static function unserialize(string $serialized): self
    {
        /** @var array $data */
        $data = json_decode($serialized, true);

        return new self($data['type'], array_map(function (string $operationType): OperationType {
            return new OperationType($operationType);
        }, $data['operation_types']));
    }

    private function __construct(string $type, array $operationTypes = [])
    {
        Assert::allOneOf(
            array_map(function (OperationType $operationType): string {
                return $operationType->__toString();
            }, $operationTypes), [
                OperationType::read()->__toString(),
                OperationType::write()->__toString(),
            ]
        );

        $this->type = $type;
        $this->operationTypes = $operationTypes;
    }

    public function operationTypes(): array
    {
        return $this->operationTypes;
    }

    public function addOperationType(OperationType $operationType): void
    {
        if (in_array($operationType, $this->operationTypes, true)) {
            return;
        }

        $this->operationTypes[] = $operationType;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function equals(self $permission): bool
    {
        $isOfTheSameType = $permission->type() === $this->type();

        $hasTheSameOperationsAllowed = true;

        foreach ($permission->operationTypes() as $operationType) {
            if (!in_array($operationType, $this->operationTypes(), true)) {
                $hasTheSameOperationsAllowed = false;
            }
        }

        return $isOfTheSameType && $hasTheSameOperationsAllowed;
    }
}
