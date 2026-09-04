<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\TwigHooks;

use Odiseo\SyliusRbacPlugin\TwigHooks\PermissionGatedHookableRenderer;
use PHPUnit\Framework\TestCase;
use Sylius\TwigHooks\Hookable\HookableTemplate;
use Sylius\TwigHooks\Hookable\Metadata\HookableMetadata;
use Sylius\TwigHooks\Hookable\Renderer\SupportableHookableRendererInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PermissionGatedHookableRendererTest extends TestCase
{
    public function testItDoesNotSupportAHookWithNoDeclaredPermission(): void
    {
        $renderer = new PermissionGatedHookableRenderer($this->createMock(AuthorizationCheckerInterface::class), []);

        self::assertFalse($renderer->supports($this->hookable()));
    }

    public function testItSupportsAHookWithADeclaredPermission(): void
    {
        $renderer = new PermissionGatedHookableRenderer($this->createMock(AuthorizationCheckerInterface::class), []);

        self::assertTrue($renderer->supports($this->hookable('sylius.statistics.view')));
    }

    /** A missing widget is a gap in the layout, not a reason to break the page. */
    public function testItRendersNothingWhenThePermissionIsNotGranted(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('sylius.statistics.view')->willReturn(false);

        $renderer = new PermissionGatedHookableRenderer($checker, []);

        self::assertSame('', $renderer->render($this->hookable('sylius.statistics.view'), $this->createMock(HookableMetadata::class)));
    }

    /** Once granted, Sylius' own renderer does the actual work -- unmodified. */
    public function testItDelegatesToWhicheverRendererSupportsTheHookableOnceGranted(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('sylius.statistics.view')->willReturn(true);

        $hookable = $this->hookable('sylius.statistics.view');
        $metadata = $this->createMock(HookableMetadata::class);

        $unrelatedDelegate = $this->createMock(SupportableHookableRendererInterface::class);
        $unrelatedDelegate->method('supports')->willReturn(false);
        $unrelatedDelegate->expects(self::never())->method('render');

        $matchingDelegate = $this->createMock(SupportableHookableRendererInterface::class);
        $matchingDelegate->method('supports')->willReturn(true);
        $matchingDelegate->expects(self::once())->method('render')->with($hookable, $metadata)->willReturn('<div>rendered</div>');

        $renderer = new PermissionGatedHookableRenderer($checker, [$unrelatedDelegate, $matchingDelegate]);

        self::assertSame('<div>rendered</div>', $renderer->render($hookable, $metadata));
    }

    public function testItRefusesToRenderAGrantedHookableNoDelegateSupports(): void
    {
        $this->expectException(\LogicException::class);

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $renderer = new PermissionGatedHookableRenderer($checker, []);

        $renderer->render($this->hookable('sylius.statistics.view'), $this->createMock(HookableMetadata::class));
    }

    /** A container has no permission of its own, and must not open onto nothing. */
    public function testItGrantsAContainerWhenAnyOfItsPermissionsIsGranted(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['sylius.customer.update', null, false],
            ['sylius.shop_user.delete', null, true],
        ]);

        $hookable = $this->hookable(['sylius.customer.update', 'sylius.shop_user.delete']);

        $delegate = $this->createMock(SupportableHookableRendererInterface::class);
        $delegate->method('supports')->willReturn(true);
        $delegate->method('render')->willReturn('<div>actions</div>');

        self::assertSame('<div>actions</div>', (new PermissionGatedHookableRenderer($checker, [$delegate]))
            ->render($hookable, $this->createMock(HookableMetadata::class)));
    }

    public function testItDeniesAContainerWhenEveryOneOfItsPermissionsIsDenied(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $hookable = $this->hookable(['sylius.customer.update', 'sylius.shop_user.delete']);

        self::assertSame('', (new PermissionGatedHookableRenderer($checker, []))
            ->render($hookable, $this->createMock(HookableMetadata::class)));
    }

    /** An empty list is no declaration at all, not a hookable nothing can ever grant. */
    public function testItDoesNotSupportAHookWhosePermissionListIsEmpty(): void
    {
        $renderer = new PermissionGatedHookableRenderer($this->createMock(AuthorizationCheckerInterface::class), []);

        self::assertFalse($renderer->supports($this->hookable([])));
    }

    /** @param string|list<string>|null $permission */
    private function hookable(string|array|null $permission = null): HookableTemplate
    {
        return new HookableTemplate(
            'sylius_admin.dashboard.index.content',
            'statistics',
            template: '@SyliusAdmin/dashboard/index/component/statistics.html.twig',
            configuration: null === $permission ? [] : ['permission' => $permission],
        );
    }
}
