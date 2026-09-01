<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security\Http;

use Odiseo\SyliusRbacPlugin\Permission\EntityAutocompletePermissionResolverInterface;
use Odiseo\SyliusRbacPlugin\Security\Http\AdminRouteAuthorizationListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminRouteAuthorizationListenerTest extends TestCase
{
    private const DECLARED = ['sylius_admin_impersonate_user' => 'sylius.impersonation.execute'];

    private const PUBLIC_ROUTES = ['sylius_admin_login'];

    /** Nothing else ever reads the route_permissions declarations. */
    public function testItEnforcesADeclaredPermission(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/sylius\.impersonation\.execute/');

        $this->listen('sylius_admin_impersonate_user', '/admin/impersonate', granted: false);
    }

    public function testItLetsADeclaredPermissionThroughWhenGranted(): void
    {
        $this->listen('sylius_admin_impersonate_user', '/admin/impersonate', granted: true);

        $this->expectNotToPerformAssertions();
    }

    public function testAPublicRouteIsNeverChecked(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen('sylius_admin_login', '/admin/login', checker: $checker);
    }

    /**
     * The resource controller checks these with the identifier its own metadata builds. Checking
     * again here would either duplicate the work or, worse, disagree with it.
     */
    public function testARouteThatChecksItselfIsLeftAlone(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen('sylius_admin_product_index', '/admin/products/', checker: $checker, sylius: ['permission' => true]);
    }

    public function testAnUncoveredAdminRouteIsDeniedByDefault(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/no permission covers it/');

        $this->listen('some_plugin_admin_screen', '/admin/some-plugin/');
    }

    public function testAnUncoveredAdminRouteIsLetThroughWhenDenyingIsTurnedOff(): void
    {
        $this->listen('some_plugin_admin_screen', '/admin/some-plugin/', denyUnprotected: false);

        $this->expectNotToPerformAssertions();
    }

    /** Routes outside the admin path are none of this listener's business. */
    public function testItIgnoresEverythingOutsideTheAdmin(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen('sylius_shop_homepage', '/en_US/', checker: $checker);
    }

    /** A sub-request re-checking the same permission would turn a rendered fragment into a 403. */
    public function testItIgnoresSubRequests(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen('some_plugin_admin_screen', '/admin/whatever', checker: $checker, main: false);
    }

    /**
     * The route is shared by every autocomplete field, so its permission cannot be a single
     * declaration — it goes through the resolver instead.
     */
    public function testEntityAutocompleteIsCheckedAgainstWhatTheResolverResolves(): void
    {
        $resolver = $this->createMock(EntityAutocompletePermissionResolverInterface::class);
        $resolver->method('resolve')->with('sylius_admin_taxon')->willReturn('sylius.taxon.index');

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with('sylius.taxon.index')->willReturn(true);

        $this->listen(
            EntityAutocompletePermissionResolverInterface::ROUTE,
            '/admin/autocomplete/sylius_admin_taxon',
            checker: $checker,
            resolver: $resolver,
            attributes: ['alias' => 'sylius_admin_taxon'],
        );
    }

    public function testEntityAutocompleteIsDeniedWhenTheResolvedPermissionIsNotGranted(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/sylius\.taxon\.index/');

        $resolver = $this->createMock(EntityAutocompletePermissionResolverInterface::class);
        $resolver->method('resolve')->willReturn('sylius.taxon.index');

        $this->listen(
            EntityAutocompletePermissionResolverInterface::ROUTE,
            '/admin/autocomplete/sylius_admin_taxon',
            granted: false,
            resolver: $resolver,
            attributes: ['alias' => 'sylius_admin_taxon'],
        );
    }

    /** Nothing to fall back to: an alias the resolver cannot place is denied, not let through. */
    public function testEntityAutocompleteIsDeniedWhenNothingCanBeResolved(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/No permission could be resolved/');

        $resolver = $this->createMock(EntityAutocompletePermissionResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);

        $this->listen(
            EntityAutocompletePermissionResolverInterface::ROUTE,
            '/admin/autocomplete/some_third_party_alias',
            resolver: $resolver,
            attributes: ['alias' => 'some_third_party_alias'],
        );
    }

    private function listen(
        string $route,
        string $path,
        bool $granted = true,
        ?AuthorizationCheckerInterface $checker = null,
        array $sylius = [],
        bool $denyUnprotected = true,
        bool $main = true,
        ?EntityAutocompletePermissionResolverInterface $resolver = null,
        array $attributes = [],
    ): void {
        if (null === $checker) {
            $checker = $this->createMock(AuthorizationCheckerInterface::class);
            $checker->method('isGranted')->willReturn($granted);
        }

        if (null === $resolver) {
            $resolver = $this->createMock(EntityAutocompletePermissionResolverInterface::class);
            $resolver->method('resolve')->willReturn(null);
        }

        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        if ([] !== $sylius) {
            $request->attributes->set('_sylius', $sylius);
        }

        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn (): null => null,
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );

        (new AdminRouteAuthorizationListener($checker, self::DECLARED, self::PUBLIC_ROUTES, $resolver, $denyUnprotected))
            ->onKernelController($event);
    }
}
