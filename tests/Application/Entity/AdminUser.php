<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Application\Entity;

use Doctrine\ORM\Mapping\MappedSuperclass;
use Doctrine\ORM\Mapping\Table;
use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;

/**
 * @MappedSuperclass()
 * @Table(name="sylius_admin_user")
 * @final
 */
class AdminUser extends BaseAdminUser implements AdminUserInterface, AdministrationRoleAwareInterface
{
    use AdministrationRoleAwareTrait;
}
