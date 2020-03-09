<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Cli\Granter;

interface AdministratorAccessGranterInterface
{
    public function __invoke(string $email, string $roleName, array $sections): void;
}
