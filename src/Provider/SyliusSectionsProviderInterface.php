<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Provider;

interface SyliusSectionsProviderInterface
{
    public function __invoke(): array;
}
