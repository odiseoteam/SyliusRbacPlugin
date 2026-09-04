<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class AdministrationRoleRepository extends EntityRepository implements AdministrationRoleRepositoryInterface
{
    /**
     * The grid sorts and filters on the role name, which now lives in the translation table, so
     * the list query has to join it. Without this the grid could only work with `code`.
     */
    public function createListQueryBuilder(string $localeCode): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->leftJoin('o.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->setParameter('localeCode', $localeCode)
        ;
    }

    /**
     * Looks the role up by any of its translated names, in any locale.
     *
     * Capped at one result on purpose: the same name usually exists in several locales, so the
     * join returns a row per locale for what is one role.
     */
    public function findOneByName(string $name): ?AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface|null $role */
        $role = $this->createQueryBuilder('o')
            ->innerJoin('o.translations', 'translation')
            ->andWhere('translation.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $role;
    }
}
