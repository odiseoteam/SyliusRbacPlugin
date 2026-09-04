<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Stands in for the application's AdminUser: a security user that can also hold roles. */
final class RoleAwareAdministrator implements AdministrationRoleAwareInterface, UserInterface
{
    use AdministrationRoleAwareTrait;

    /**
     * @param list<list<string>> $roles one list of patterns per role
     */
    public function __construct(array $roles = [])
    {
        $this->administrationRoles = new ArrayCollection();

        foreach ($roles as $index => $patterns) {
            $this->addAdministrationRole(self::role('role_' . $index, $patterns));
        }
    }

    /**
     * @param list<string> $patterns
     */
    public static function role(string $code, array $patterns): AdministrationRoleInterface
    {
        $role = new AdministrationRole();
        $role->setCode($code);

        foreach ($patterns as $pattern) {
            $role->addPermissionPattern(\Odiseo\SyliusRbacPlugin\Permission\PermissionPattern::fromString($pattern));
        }

        return $role;
    }

    /** @return Collection<array-key, AdministrationRoleInterface> */
    public function roles(): Collection
    {
        return $this->getAdministrationRoles();
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_ADMINISTRATION_ACCESS'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'administrator';
    }
}
