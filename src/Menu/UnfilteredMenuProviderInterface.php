<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Menu;

use Knp\Menu\ItemInterface;

interface UnfilteredMenuProviderInterface
{
    /** The admin menu as it would look to an administrator who holds everything. */
    public function menu(): ?ItemInterface;
}
