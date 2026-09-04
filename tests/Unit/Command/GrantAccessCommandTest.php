<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Command;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Command\GrantAccessCommand;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GrantAccessCommandTest extends TestCase
{
    private ?AdministrationRoleInterface $existingRole = null;

    /** @var array<string, AdministrationRoleAwareInterface> */
    private array $administrators = [];

    public function testItGivesAnExistingRoleToAnAdministrator(): void
    {
        $role = $this->role('super_admin', ['*.*.*']);
        $this->existingRole = $role;
        $administrator = $this->administrator('ada');

        $tester = $this->execute(['administrator' => 'ada', 'role' => 'super_admin']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertTrue($administrator->hasAdministrationRole($role));
    }

    /** Looked up by either, so whoever is locked out does not also have to guess which one it is. */
    public function testItFindsTheAdministratorByEmailToo(): void
    {
        $this->existingRole = $this->role('super_admin', ['*.*.*']);
        $administrator = $this->administrator('ada', 'ada@example.com');

        $this->execute(['administrator' => 'ada@example.com', 'role' => 'super_admin']);

        self::assertCount(1, $administrator->getAdministrationRoles());
    }

    /**
     * "Every role was deleted" is one of the states this exists to repair, and assigning an
     * existing role cannot fix that one.
     */
    public function testItCreatesTheRoleGrantingEverythingWhenAskedTo(): void
    {
        $administrator = $this->administrator('ada');

        $tester = $this->execute(['administrator' => 'ada', 'role' => 'super_admin', '--create' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        /** @var AdministrationRoleInterface $granted */
        $granted = $administrator->getAdministrationRoles()->first();

        self::assertSame('super_admin', $granted->getCode());
        self::assertSame(['*.*.*'], $granted->getPermissions());
    }

    /** Minting a role is never a side effect of a typo in its code. */
    public function testItRefusesAnUnknownRoleWithoutCreate(): void
    {
        $administrator = $this->administrator('ada');

        $tester = $this->execute(['administrator' => 'ada', 'role' => 'typo']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--create', $tester->getDisplay());
        self::assertCount(0, $administrator->getAdministrationRoles());
    }

    public function testItFailsWhenNoSuchAdministratorExists(): void
    {
        $this->existingRole = $this->role('super_admin', ['*.*.*']);

        $tester = $this->execute(['administrator' => 'nobody', 'role' => 'super_admin']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testItWritesNothingOnADryRun(): void
    {
        $this->existingRole = $this->role('super_admin', ['*.*.*']);
        $administrator = $this->administrator('ada');

        $tester = $this->execute(['administrator' => 'ada', 'role' => 'super_admin', '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertCount(0, $administrator->getAdministrationRoles());
    }

    public function testItIsIdempotent(): void
    {
        $role = $this->role('super_admin', ['*.*.*']);
        $this->existingRole = $role;
        $administrator = $this->administrator('ada');
        $administrator->addAdministrationRole($role);

        $tester = $this->execute(['administrator' => 'ada', 'role' => 'super_admin']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertCount(1, $administrator->getAdministrationRoles());
    }

    /**
     * The first question is whether the lockout is real: if somebody else can still reach the
     * roles screen, this command is the wrong tool.
     */
    public function testItReportsWhoCanManageRolesBeforeChangingAnything(): void
    {
        $this->existingRole = $this->role('super_admin', ['*.*.*']);
        $this->administrator('grace')->addAdministrationRole($this->role('other', ['odiseo_rbac.administration_role.*']));
        $this->administrator('ada');

        $display = $this->execute(['administrator' => 'ada', 'role' => 'super_admin', '--dry-run' => true])->getDisplay();

        self::assertStringContainsString('grace', $display);
    }

    public function testItSaysSoWhenNobodyCanManageRolesAtAll(): void
    {
        $this->administrator('ada')->addAdministrationRole($this->role('catalog', ['sylius.product.*']));

        $display = $this->execute(['administrator' => 'ada', 'role' => 'super_admin', '--create' => true])->getDisplay();

        self::assertStringContainsString('No administrator can currently manage roles', $display);
    }

    /** @param array<string, mixed> $input */
    private function execute(array $input): CommandTester
    {
        $roles = $this->createMock(RepositoryInterface::class);
        $roles->method('findOneBy')->willReturnCallback(
            fn (array $criteria): ?AdministrationRoleInterface => null !== $this->existingRole &&
                $this->existingRole->getCode() === $criteria['code'] ? $this->existingRole : null,
        );

        $users = $this->createMock(RepositoryInterface::class);
        $users->method('findAll')->willReturnCallback(fn (): array => array_values($this->administrators));
        $users->method('findOneBy')->willReturnCallback(
            fn (array $criteria): ?AdministrationRoleAwareInterface => $this->administrators[reset($criteria)] ?? null,
        );

        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): AdministrationRole => new AdministrationRole());

        $locales = $this->createMock(RepositoryInterface::class);
        $locale = new Locale();
        $locale->setCode('en_US');
        $locales->method('findAll')->willReturn([$locale]);

        $tester = new CommandTester(new GrantAccessCommand(
            $users,
            $roles,
            $factory,
            $locales,
            $this->createMock(ObjectManager::class),
        ));

        $tester->execute($input);

        return $tester;
    }

    /** @param list<string> $permissions */
    private function role(string $code, array $permissions): AdministrationRoleInterface
    {
        $role = new AdministrationRole();
        $role->setCode($code);
        $role->setPermissions($permissions);

        return $role;
    }

    private function administrator(string $username, ?string $email = null): AdministrationRoleAwareInterface
    {
        $administrator = new class() implements AdministrationRoleAwareInterface {
            use AdministrationRoleAwareTrait;

            public string $username = '';

            public function getUsername(): string
            {
                return $this->username;
            }
        };

        $administrator->username = $username;

        $this->administrators[$username] = $administrator;

        if (null !== $email) {
            $this->administrators[$email] = $administrator;
        }

        return $administrator;
    }
}
