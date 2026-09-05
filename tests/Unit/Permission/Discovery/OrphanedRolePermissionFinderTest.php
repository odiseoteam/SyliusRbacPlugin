<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\OrphanedRolePermissionFinder;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistry;
use Odiseo\SyliusRbacPlugin\Repository\AdministrationRoleRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class OrphanedRolePermissionFinderTest extends TestCase
{
    public function testARoleHoldingAKnownPermissionIsNotReported(): void
    {
        $role = $this->roleWith('catalog_manager', 'sylius.product.index');

        $result = $this->find(['sylius.product.index'], [$role]);

        self::assertSame([], $result);
    }

    /** A rename or an uninstalled plugin leaves the string behind; nothing else catches it. */
    public function testARoleHoldingAPermissionNothingDeclaresAnymoreIsReported(): void
    {
        $role = $this->roleWith('catalog_manager', 'sylius.product.view');

        $result = $this->find(['sylius.product.index'], [$role]);

        self::assertSame(['catalog_manager' => ['sylius.product.view']], $result);
    }

    /** Meant to keep matching whatever gets added later, so matching nothing today is not a sign of trouble. */
    public function testAWildcardIsNeverReportedEvenWhenItMatchesNothing(): void
    {
        $role = $this->roleWith('empty_plugin_admin', 'sylius_mollie.*.*');

        $result = $this->find([], [$role]);

        self::assertSame([], $result);
    }

    public function testEveryOrphanedPatternOfARoleIsReportedTogether(): void
    {
        $role = $this->createMock(AdministrationRoleInterface::class);
        $role->method('getCode')->willReturn('legacy_role');
        $role->method('getPermissionPatterns')->willReturn([
            PermissionPattern::fromString('sylius.product.view'),
            PermissionPattern::fromString('sylius.order.cancel'),
        ]);

        $result = $this->find([], [$role]);

        self::assertSame(['legacy_role' => ['sylius.product.view', 'sylius.order.cancel']], $result);
    }

    public function testItFindsNothingWhenThereAreNoRoles(): void
    {
        self::assertSame([], $this->find(['sylius.product.index'], []));
    }

    private function roleWith(string $code, string $pattern): AdministrationRoleInterface
    {
        $role = $this->createMock(AdministrationRoleInterface::class);
        $role->method('getCode')->willReturn($code);
        $role->method('getPermissionPatterns')->willReturn([PermissionPattern::fromString($pattern)]);

        return $role;
    }

    /**
     * @param list<string> $knownIdentifiers
     * @param list<AdministrationRoleInterface> $roles
     *
     * @return array<string, list<string>>
     */
    private function find(array $knownIdentifiers, array $roles): array
    {
        $registry = new PermissionRegistry(array_map(
            static fn (string $identifier): PermissionDefinition => new PermissionDefinition(PermissionIdentifier::fromString($identifier)),
            $knownIdentifiers,
        ));

        $roleRepository = $this->createMock(AdministrationRoleRepositoryInterface::class);
        $roleRepository->method('findAll')->willReturn($roles);

        return (new OrphanedRolePermissionFinder($registry, $roleRepository))->find();
    }
}
