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

    private function hookable(?string $permission = null): HookableTemplate
    {
        return new HookableTemplate(
            'sylius_admin.dashboard.index.content',
            'statistics',
            template: '@SyliusAdmin/dashboard/index/component/statistics.html.twig',
            configuration: null === $permission ? [] : ['permission' => $permission],
        );
    }
}
