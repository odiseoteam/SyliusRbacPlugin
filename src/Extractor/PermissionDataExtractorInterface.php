<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Extractor;

interface PermissionDataExtractorInterface
{
    public function extract(array $permissions): array;
}
