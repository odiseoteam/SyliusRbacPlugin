<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Provider;

final class SyliusSectionsProvider implements SyliusSectionsProviderInterface
{
    private const CUSTOM_SECTION_CONFIGURATION_KEY = 'custom';

    public function __construct(
        private array $rbacConfiguration
    ) {
    }

    public function __invoke(): array
    {
        $mergedArray = array_diff(
            array_merge(
                array_keys($this->rbacConfiguration),
                array_keys($this->rbacConfiguration[self::CUSTOM_SECTION_CONFIGURATION_KEY])
            ),
            [self::CUSTOM_SECTION_CONFIGURATION_KEY]
        );

        return $this->rearrangeArray($mergedArray);
    }

    private function rearrangeArray(array $arrayToRearrange): array
    {
        return array_values($arrayToRearrange);
    }
}
