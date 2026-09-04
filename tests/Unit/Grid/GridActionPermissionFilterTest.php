<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Grid;

use Odiseo\SyliusRbacPlugin\Grid\GridActionPermissionFilter;
use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Grid\Definition\Action;
use Sylius\Component\Grid\Definition\ActionGroup;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Resource\Metadata\MetadataInterface;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class GridActionPermissionFilterTest extends TestCase
{
    private const MODEL = 'Sylius\\Component\\Core\\Model\\Product';

    public function testItDisablesActionsTheAdministratorCannotPerform(): void
    {
        $grid = $this->filter(['sylius.product.index', 'sylius.product.update']);

        self::assertTrue($grid->getActionGroup('item')->getAction('update')->isEnabled());
        self::assertFalse($grid->getActionGroup('item')->getAction('delete')->isEnabled());
        self::assertFalse($grid->getActionGroup('main')->getAction('create')->isEnabled());
    }

    /**
     * The same button type means a different permission depending on where it sits: `delete` in
     * the bulk group deletes every selected row, which Sylius checks as `bulk_delete`.
     */
    public function testBulkDeleteAsksForBulkDeleteRatherThanDelete(): void
    {
        $granted = $this->filter(['sylius.product.bulk_delete']);
        self::assertTrue($granted->getActionGroup('bulk')->getAction('delete')->isEnabled());

        $withPlainDelete = $this->filter(['sylius.product.delete']);
        self::assertFalse($withPlainDelete->getActionGroup('bulk')->getAction('delete')->isEnabled());
    }

    public function testAnActionCarryingARouteIsResolvedThroughTheRouteMap(): void
    {
        $denied = $this->filter([]);
        self::assertFalse($denied->getActionGroup('item')->getAction('impersonate')->isEnabled());

        $allowed = $this->filter(['sylius.impersonation.execute']);
        self::assertTrue($allowed->getActionGroup('item')->getAction('impersonate')->isEnabled());
    }

    /** A dropdown keeps the destinations that are allowed and drops the rest. */
    public function testALinksActionKeepsOnlyThePermittedLinks(): void
    {
        $grid = $this->filter(['sylius.product.create']);
        $action = $grid->getActionGroup('main')->getAction('create_variants');

        self::assertTrue($action->isEnabled());
        self::assertSame(['simple'], array_keys($action->getOptions()['links']));
    }

    public function testALinksActionDisappearsWhenNoneOfItsLinksIsPermitted(): void
    {
        $grid = $this->filter([]);

        self::assertFalse($grid->getActionGroup('main')->getAction('create_variants')->isEnabled());
    }

    public function testAnActionItCannotNameIsLeftAlone(): void
    {
        $grid = $this->filter([]);

        self::assertTrue($grid->getActionGroup('item')->getAction('mystery')->isEnabled());
    }

    /**
     * @param list<string> $granted
     */
    private function filter(array $granted): Grid
    {
        $grid = Grid::fromCodeAndDriverConfiguration('sylius_admin_product', 'doctrine/orm', ['class' => self::MODEL]);

        $main = ActionGroup::named('main');
        $main->addAction(Action::fromNameAndType('create', 'create'));
        $createVariants = Action::fromNameAndType('create_variants', 'links');
        $createVariants->setOptions(['links' => [
            'simple' => ['route' => 'sylius_admin_product_create_simple'],
            'configurable' => ['route' => 'sylius_admin_product_create_configurable'],
        ]]);
        $main->addAction($createVariants);
        $grid->addActionGroup($main);

        $item = ActionGroup::named('item');
        $item->addAction(Action::fromNameAndType('update', 'update'));
        $item->addAction(Action::fromNameAndType('delete', 'delete'));
        $impersonate = Action::fromNameAndType('impersonate', 'link');
        $impersonate->setOptions(['link' => ['route' => 'sylius_admin_impersonate_user']]);
        $item->addAction($impersonate);
        $item->addAction(Action::fromNameAndType('mystery', 'something_custom'));
        $grid->addActionGroup($item);

        $bulk = ActionGroup::named('bulk');
        $bulk->addAction(Action::fromNameAndType('delete', 'delete'));
        $grid->addActionGroup($bulk);

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => in_array($attribute, $granted, true),
        );

        $map = $this->createMock(RoutePermissionMapInterface::class);
        $map->method('permissionFor')->willReturnCallback(static fn (string $route): ?string => match ($route) {
            'sylius_admin_impersonate_user' => 'sylius.impersonation.execute',
            'sylius_admin_product_create_simple' => 'sylius.product.create',
            'sylius_admin_product_create_configurable' => 'sylius.product_variant.create',
            default => null,
        });

        $metadata = $this->createMock(MetadataInterface::class);
        $metadata->method('getPermissionCode')->willReturnCallback(
            static fn (string $operation): string => 'sylius.product.' . $operation,
        );

        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getByClass')->willReturn($metadata);

        (new GridActionPermissionFilter($checker, $map, $registry))->filter($grid);

        return $grid;
    }
}
