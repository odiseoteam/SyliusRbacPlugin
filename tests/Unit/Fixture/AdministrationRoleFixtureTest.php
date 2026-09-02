<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Fixture;

use Doctrine\Persistence\ObjectManager;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Fixture\AdministrationRoleFixture;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class AdministrationRoleFixtureTest extends TestCase
{
    public function testItCreatesTheRoleWithItsPatternsStoredAsWritten(): void
    {
        $created = new AdministrationRole();

        $this->fixture($created, null)->load([
            'code' => 'read_only',
            'name' => 'Read only',
            'permissions' => ['*.*.index', '*.*.show'],
        ]);

        self::assertSame('read_only', $created->getCode());
        self::assertSame(['*.*.index', '*.*.show'], $created->getPermissions());
    }

    /** A second load must reuse the row instead of hitting the unique constraint on `code`. */
    public function testItReusesARoleThatAlreadyHasTheCode(): void
    {
        $existing = new AdministrationRole();
        $existing->setCode('super_admin');

        $factory = $this->createMock(FactoryInterface::class);
        $factory->expects(self::never())->method('createNew');

        $this->fixture(new AdministrationRole(), $existing, $factory)->load([
            'code' => 'super_admin',
            'name' => 'Super Admin',
            'permissions' => ['*.*.*'],
        ]);

        self::assertSame(['*.*.*'], $existing->getPermissions());
    }

    /** Reusing a row must not leave yesterday's patterns sitting under today's. */
    public function testItReplacesThePatternsOfAReusedRoleRatherThanAddingToThem(): void
    {
        $existing = new AdministrationRole();
        $existing->setCode('catalog');
        $existing->setPermissions(['sylius.order.*', '*.*.index']);

        $this->fixture(new AdministrationRole(), $existing)->load([
            'code' => 'catalog',
            'name' => 'Catalog',
            'permissions' => ['sylius.product.*'],
        ]);

        self::assertSame(['sylius.product.*'], $existing->getPermissions());
    }

    private function fixture(
        AdministrationRoleInterface $created,
        ?AdministrationRoleInterface $existing,
        ?FactoryInterface $factory = null,
    ): AdministrationRoleFixture {
        $factory ??= $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturn($created);

        $roles = $this->createMock(RepositoryInterface::class);
        $roles->method('findOneBy')->willReturn($existing);

        $locales = $this->createMock(RepositoryInterface::class);
        $locales->method('findAll')->willReturn([$this->locale('en_US')]);

        return new AdministrationRoleFixture(
            $factory,
            $this->createMock(ObjectManager::class),
            $locales,
            $roles,
        );
    }

    private function locale(string $code): LocaleInterface
    {
        $locale = new Locale();
        $locale->setCode($code);

        return $locale;
    }
}
