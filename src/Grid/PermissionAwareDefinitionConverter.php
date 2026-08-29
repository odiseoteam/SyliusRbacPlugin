<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Grid;

use Sylius\Component\Grid\Definition\ArrayToDefinitionConverterInterface;
use Sylius\Component\Grid\Definition\Grid;

/**
 * Applies the permission filter to every grid, without naming any of them.
 *
 * Decorating the converter is what makes this cover grids the plugin has never heard of: a
 * mutator would have to be registered per grid code, and the list of grid codes in an
 * application is not something this plugin can know.
 */
final readonly class PermissionAwareDefinitionConverter implements ArrayToDefinitionConverterInterface
{
    public function __construct(
        private ArrayToDefinitionConverterInterface $decorated,
        private GridActionPermissionFilter $filter,
    ) {
    }

    public function convert(string $code, array $configuration): Grid
    {
        $grid = $this->decorated->convert($code, $configuration);

        $this->filter->filter($grid);

        return $grid;
    }
}
