<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Doctrine\Common\Collections\Collection;

/**
 * An administrator holds several roles, and their permissions are the union of all of them.
 *
 * The pre-v3 model allowed exactly one role per administrator, which forced shops to create a
 * combinatorial role per person — "catalog + orders", "catalog + orders + customers" — instead
 * of composing two roles they already had.
 */
interface AdministrationRoleAwareInterface
{
    /** @return Collection<array-key, AdministrationRoleInterface> */
    public function getAdministrationRoles(): Collection;

    public function addAdministrationRole(AdministrationRoleInterface $administrationRole): void;

    public function removeAdministrationRole(AdministrationRoleInterface $administrationRole): void;

    public function hasAdministrationRole(AdministrationRoleInterface $administrationRole): bool;
}
