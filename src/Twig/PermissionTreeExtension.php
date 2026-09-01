<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Twig;

use Odiseo\SyliusRbacPlugin\Permission\PermissionGroup;
use Odiseo\SyliusRbacPlugin\Permission\PermissionTree;
use Odiseo\SyliusRbacPlugin\Permission\PermissionTreeInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PermissionTreeExtension extends AbstractExtension
{
    public function __construct(private readonly PermissionTreeInterface $tree)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('odiseo_rbac_permission_tree', $this->groups(...)),
            new TwigFunction('odiseo_rbac_read_operations', static fn (): array => PermissionTree::READ_COLUMNS),
            new TwigFunction('odiseo_rbac_permission_identifiers', $this->identifiers(...)),
        ];
    }

    /** @return list<PermissionGroup> */
    public function groups(): array
    {
        return $this->tree->groups();
    }

    /**
     * Every identifier, so the editor can work out what a wildcard actually covers.
     *
     * @return list<string>
     */
    public function identifiers(): array
    {
        $identifiers = [];

        foreach ($this->groups() as $group) {
            foreach ($group->subjects() as $subject) {
                foreach (array_keys($subject->operations()) as $operation) {
                    $identifiers[] = $subject->identifier($operation);
                }
            }
        }

        return $identifiers;
    }
}
