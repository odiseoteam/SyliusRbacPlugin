<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface AdministrationRoleRepositoryInterface extends RepositoryInterface
{
    public function createListQueryBuilder(string $localeCode): QueryBuilder;

    public function findOneByName(string $name): ?AdministrationRoleInterface;
}
