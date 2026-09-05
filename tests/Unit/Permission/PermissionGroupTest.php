<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission;

use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionGroup;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use PHPUnit\Framework\TestCase;

final class PermissionGroupTest extends TestCase
{
    public function testItCountsEveryPermissionItHolds(): void
    {
        $group = $this->group([
            'sylius.order.index' => 'Orders',
            'sylius.order.show' => 'Orders',
            'sylius.payment.index' => 'Payments',
        ]);

        self::assertSame(3, $group->permissionCount());
        self::assertSame(['Orders', 'Payments'], array_map(
            static fn ($subject): string => $subject->label,
            $group->subjects(),
        ));
    }

    /**
     * A section whose subjects are capabilities rather than resources would otherwise show six
     * CRUD columns of dots to hold a single checkbox. `view` is not one of the shared six, so
     * such a section ends up with no columns at all and only the operations of its own rows.
     */
    public function testItOnlyPublishesTheColumnsItsSubjectsUse(): void
    {
        $group = $this->group([
            'sylius.dashboard.view' => 'Dashboard',
            'sylius.statistics.view' => 'Statistics',
        ]);

        self::assertSame([], $group->columns());
        self::assertTrue($group->hasExtraOperations());
    }

    /**
     * A column has to say something comparable about every row to earn its width. `cancel` and
     * `ship` belong to one subject each, so they render inside the row instead -- see
     * `PermissionSubjectTest`.
     */
    public function testItOrdersTheSharedColumnsAsTheyAreReadAndLeavesTheRestOut(): void
    {
        $group = $this->group([
            'sylius.order.ship' => 'Orders',
            'sylius.order.update' => 'Orders',
            'sylius.order.cancel' => 'Orders',
            'sylius.order.index' => 'Orders',
        ]);

        self::assertSame(['index', 'update'], $group->columns());
    }

    public function testItAnnouncesTheColumnHoldingTheOperationsThatAreASubjectsOwn(): void
    {
        self::assertTrue($this->group([
            'sylius.order.index' => 'Orders',
            'sylius.order.cancel' => 'Orders',
        ])->hasExtraOperations());

        self::assertFalse($this->group([
            'sylius.product.index' => 'Products',
            'sylius.product.update' => 'Products',
        ])->hasExtraOperations());
    }

    public function testItPutsASubjectReachedFromInsideAnotherOneRightAfterIt(): void
    {
        $group = new PermissionGroup('catalog');
        $group->add($this->definition('sylius.taxon.index'), 'Taxons');
        $group->add($this->definition('sylius.product.index'), 'Products');
        $group->add($this->definition('sylius.product_taxon.index'), 'Taxa', 'sylius.product');

        self::assertSame(['Products', 'Taxa', 'Taxons'], array_map(
            static fn ($subject): string => $subject->label,
            $group->subjects(),
        ));
    }

    /** Rows follow the admin menu, not the alphabet — a subject the menu never links to falls back to it. */
    public function testItOrdersSubjectsByTheirMenuPositionRatherThanAlphabetically(): void
    {
        $group = new PermissionGroup('sales', ['sylius.order' => 1, 'sylius.customer' => 0]);
        $group->add($this->definition('sylius.order.index'), 'Orders');
        $group->add($this->definition('sylius.customer.index'), 'Customers');
        $group->add($this->definition('sylius.payment.index'), 'Payments');

        self::assertSame(['Customers', 'Orders', 'Payments'], array_map(
            static fn ($subject): string => $subject->label,
            $group->subjects(),
        ));
    }

    /** A parent filed in another group cannot be followed, so the row stays where it is. */
    public function testItKeepsASubjectWhoseParentIsNotInThisGroupInAlphabeticalOrder(): void
    {
        $group = new PermissionGroup('catalog');
        $group->add($this->definition('sylius.taxon.index'), 'Taxons');
        $group->add($this->definition('sylius.product_taxon.index'), 'Taxa', 'sylius.product');

        self::assertSame(['Taxa', 'Taxons'], array_map(
            static fn ($subject): string => $subject->label,
            $group->subjects(),
        ));
    }

    /** @param array<string, string> $permissions identifier => subject label */
    private function group(array $permissions): PermissionGroup
    {
        $group = new PermissionGroup('a_group');

        foreach ($permissions as $identifier => $label) {
            $group->add($this->definition($identifier), $label);
        }

        return $group;
    }

    private function definition(string $identifier): PermissionDefinition
    {
        return new PermissionDefinition(PermissionIdentifier::fromString($identifier));
    }
}
