<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Entity;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRole;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use PHPUnit\Framework\TestCase;

final class AdministrationRoleTest extends TestCase
{
    private AdministrationRole $administrationRole;

    protected function setUp(): void
    {
        $this->administrationRole = new AdministrationRole();
    }

    public function testItImplementsTheInterface(): void
    {
        $this->assertInstanceOf(AdministrationRoleInterface::class, $this->administrationRole);
    }

    public function testItStartsWithoutAnIdOrName(): void
    {
        $this->assertNull($this->administrationRole->getId());
        $this->assertNull($this->administrationRole->getName());
    }

    public function testItsNameIsMutable(): void
    {
        $this->administrationRole->setName('Catalog manager');

        $this->assertSame('Catalog manager', $this->administrationRole->getName());
    }
}
