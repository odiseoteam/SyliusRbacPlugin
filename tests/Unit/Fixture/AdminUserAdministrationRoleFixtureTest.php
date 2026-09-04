<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Fixture;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Fixture\AdminUserAdministrationRoleFixture;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class AdminUserAdministrationRoleFixtureTest extends TestCase
{
    public function testItAssignsTheRoleToEveryNamedAdministrator(): void
    {
        $role = $this->role('super_admin');
        $first = $this->administrator();
        $second = $this->administrator();

        $this->fixture($role, ['ada' => $first, 'grace' => $second])
            ->load(['role' => 'super_admin', 'usernames' => ['ada', 'grace']]);

        self::assertTrue($first->hasAdministrationRole($role));
        self::assertTrue($second->hasAdministrationRole($role));
    }

    /**
     * This ships to every installation, and the usernames it carries are the ones Sylius' own
     * demo fixtures happen to create: an application that replaces them must not get a failing
     * `sylius:fixtures:load` out of a convenience it never asked for.
     */
    public function testItSkipsAUsernameNothingCreated(): void
    {
        $role = $this->role('super_admin');
        $existing = $this->administrator();

        $this->fixture($role, ['ada' => $existing])
            ->load(['role' => 'super_admin', 'usernames' => ['ada', 'nobody']]);

        self::assertTrue($existing->hasAdministrationRole($role));
    }

    public function testItFailsWhenTheRoleItIsToldToAssignDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->fixture(null, [])->load(['role' => 'nothing', 'usernames' => ['ada']]);
    }

    /** @param array<string, AdministrationRoleAwareInterface> $administrators */
    private function fixture(?AdministrationRoleInterface $role, array $administrators): AdminUserAdministrationRoleFixture
    {
        $roles = $this->createMock(RepositoryInterface::class);
        $roles->method('findOneBy')->willReturn($role);

        $users = $this->createMock(RepositoryInterface::class);
        $users->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => $administrators[$criteria['username']] ?? null,
        );

        return new AdminUserAdministrationRoleFixture($users, $roles, $this->createMock(ObjectManager::class));
    }

    private function role(string $code): AdministrationRoleInterface
    {
        $role = new AdministrationRole();
        $role->setCode($code);

        return $role;
    }

    private function administrator(): AdministrationRoleAwareInterface
    {
        return new class() implements AdministrationRoleAwareInterface {
            use AdministrationRoleAwareTrait;
        };
    }
}
