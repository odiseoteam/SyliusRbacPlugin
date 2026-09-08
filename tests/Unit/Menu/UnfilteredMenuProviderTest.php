<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Menu;

use Odiseo\SyliusRbacPlugin\Menu\MenuPermissionListener;
use Odiseo\SyliusRbacPlugin\Menu\UnfilteredMenuProvider;
use Odiseo\SyliusRbacPlugin\Permission\RoutePermissionMapInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class UnfilteredMenuProviderTest extends TestCase
{
    /**
     * `sylius_admin.menu_builder.main` doesn't exist until `sylius/sylius-admin-bundle` is
     * registered -- true right after `composer require` on a bare skeleton, before the rest of
     * Sylius is wired in. The DI reference is optional for exactly this, so the constructor has
     * to accept the resulting null without falling over.
     */
    public function testItHasNoMenuWithoutAMenuBuilder(): void
    {
        $filter = new MenuPermissionListener(
            $this->createMock(AuthorizationCheckerInterface::class),
            $this->createMock(RoutePermissionMapInterface::class),
        );

        $provider = new UnfilteredMenuProvider(null, $filter);

        self::assertNull($provider->menu());
    }
}
