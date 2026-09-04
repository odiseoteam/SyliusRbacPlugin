<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Transform;

use Behat\Behat\Context\Context;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Repository\AdministrationRoleRepositoryInterface;
use Webmozart\Assert\Assert;

final class AdministrationRoleContext implements Context
{
    public function __construct(
        private AdministrationRoleRepositoryInterface $administrationRoleRepository,
    ) {
    }

    /**
     * @Transform /^administration role "([^"]+)"$/
     * @Transform /^"([^"]+)" administration role$/
     */
    public function getAdministrationRoleByName(string $name): AdministrationRoleInterface
    {
        $administrationRole = $this->administrationRoleRepository->findOneByName($name);

        Assert::notNull($administrationRole, sprintf('Administration role with name "%s" does not exist', $name));

        return $administrationRole;
    }
}
