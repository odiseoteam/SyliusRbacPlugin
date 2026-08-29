<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Entity;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use PHPUnit\Framework\TestCase;

final class AdministrationRoleTest extends TestCase
{
    private const LOCALE = 'en_US';

    private AdministrationRole $administrationRole;

    protected function setUp(): void
    {
        $this->administrationRole = new AdministrationRole();
        $this->administrationRole->setCurrentLocale(self::LOCALE);
        $this->administrationRole->setFallbackLocale(self::LOCALE);
    }

    public function testItImplementsTheInterface(): void
    {
        self::assertInstanceOf(AdministrationRoleInterface::class, $this->administrationRole);
    }

    public function testItStartsEmpty(): void
    {
        self::assertNull($this->administrationRole->getId());
        self::assertNull($this->administrationRole->getCode());
        self::assertNull($this->administrationRole->getName());
        self::assertSame([], $this->administrationRole->getPermissionPatterns());
    }

    public function testItsCodeIsMutable(): void
    {
        $this->administrationRole->setCode('catalog_manager');

        self::assertSame('catalog_manager', $this->administrationRole->getCode());
    }

    public function testItsNameGoesThroughTheTranslation(): void
    {
        $this->administrationRole->setName('Catalog manager');

        self::assertSame('Catalog manager', $this->administrationRole->getName());
        self::assertSame('Catalog manager', $this->administrationRole->getTranslation(self::LOCALE)->getName());
    }

    public function testTheSameRoleCanBeNamedDifferentlyPerLocale(): void
    {
        $this->administrationRole->setName('Catalog manager');

        $this->administrationRole->setCurrentLocale('es_AR');
        $this->administrationRole->setFallbackLocale('es_AR');
        $this->administrationRole->setName('Gestor de catálogo');

        self::assertSame('Gestor de catálogo', $this->administrationRole->getName());

        $this->administrationRole->setCurrentLocale(self::LOCALE);
        self::assertSame('Catalog manager', $this->administrationRole->getName());
    }

    public function testItHoldsPermissionPatterns(): void
    {
        $pattern = PermissionPattern::fromString('sylius.product.*');

        self::assertFalse($this->administrationRole->hasPermissionPattern($pattern));

        $this->administrationRole->addPermissionPattern($pattern);

        self::assertTrue($this->administrationRole->hasPermissionPattern($pattern));
        self::assertEquals([$pattern], $this->administrationRole->getPermissionPatterns());
    }

    public function testAddingTheSamePatternTwiceChangesNothing(): void
    {
        $this->administrationRole->addPermissionPattern(PermissionPattern::fromString('sylius.product.*'));
        $this->administrationRole->addPermissionPattern(PermissionPattern::fromString('sylius.product.*'));

        self::assertCount(1, $this->administrationRole->getPermissionPatterns());
    }

    public function testPatternsCanBeRemovedAndCleared(): void
    {
        $this->administrationRole->addPermissionPattern(PermissionPattern::fromString('sylius.product.*'));
        $this->administrationRole->addPermissionPattern(PermissionPattern::fromString('sylius.order.index'));

        $this->administrationRole->removePermissionPattern(PermissionPattern::fromString('sylius.product.*'));

        self::assertSame(
            ['sylius.order.index'],
            array_map(static fn (PermissionPattern $p): string => $p->toString(), $this->administrationRole->getPermissionPatterns()),
        );

        $this->administrationRole->clearPermissionPatterns();

        self::assertSame([], $this->administrationRole->getPermissionPatterns());
    }

    /**
     * The role stores the pattern, not what it expands to today. Expanding at save time would
     * freeze the role: the next Sylius release that adds an operation would leave it without.
     */
    public function testAWildcardIsStoredAsWritten(): void
    {
        $this->administrationRole->addPermissionPattern(PermissionPattern::fromString('sylius.product.*'));

        self::assertSame('sylius.product.*', $this->administrationRole->getPermissionPatterns()[0]->toString());
    }
}
