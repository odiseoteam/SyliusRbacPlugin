<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Behat\Context\Transform;

use Behat\Behat\Context\Context;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Webmozart\Assert\Assert;

final class AdministrationRoleContext implements Context
{
    /** @var RepositoryInterface */
    private $administrationRoleRepository;

    public function __construct(RepositoryInterface $administrationRoleRepository)
    {
        $this->administrationRoleRepository = $administrationRoleRepository;
    }

    /**
     * @Transform :administrationRole
     * @Transform /^administration role "([^"]+)"$/
     */
    public function getAdministrationRoleByName(string $name): AdministrationRoleInterface
    {
        /** @var AdministrationRoleInterface $administrationRole */
        $administrationRole = $this->administrationRoleRepository->findOneBy(['name' => $name]);
        Assert::notNull($administrationRole, sprintf('There is no administration role with name "%s"', $name));

        return $administrationRole;
    }
}
