<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security\Api;

use Odiseo\SyliusRbacPlugin\Security\Api\AdminApiAuthorizationListener;
use Odiseo\SyliusRbacPlugin\Security\Api\ApiOperationPermissionResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminApiAuthorizationListenerTest extends TestCase
{
    /**
     * The pre-v3 engine only matched route names starting with `sylius_admin`, so every
     * `sylius_api_admin_*` route went unchecked and an administrator holding a JWT could do
     * anything at all.
     */
    public function testItDeniesAnAdminApiOperationTheAdministratorLacks(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/sylius\.order\.index/');

        $this->listen('/api/v2/admin/orders', 'sylius.order.index', granted: false);
    }

    public function testItAllowsAnAdminApiOperationTheAdministratorHolds(): void
    {
        $this->listen('/api/v2/admin/products', 'sylius.product.index', granted: true);

        $this->expectNotToPerformAssertions();
    }

    /** The shop API is not this plugin's business. */
    public function testItIgnoresTheShopApi(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen('/api/v2/shop/products', 'sylius.product.index', checker: $checker);
    }

    public function testItIgnoresTheHtmlAdmin(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen('/admin/products/', 'sylius.product.index', checker: $checker);
    }

    public function testAnOperationWithNoResolvablePermissionIsDeniedByDefault(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/no permission could be resolved/');

        $this->listen('/api/v2/admin/custom', null);
    }

    /**
     * The statistics endpoint is a plain controller, not an API Platform operation, so nothing
     * derives a permission for it. Before it was declared, an administrator holding any role at
     * all could read the shop's whole revenue history.
     */
    public function testAPlainControllerUnderTheAdminApiIsCheckedAgainstItsDeclaration(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/sylius\.statistics\.view/');

        $this->listen(
            '/api/v2/admin/statistics',
            null,
            route: 'sylius_api_admin_statistics',
            granted: false,
            withApiAttributes: false,
        );
    }

    public function testAnUndeclaredPlainControllerUnderTheAdminApiIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/no permission covers it/');

        $this->listen('/api/v2/admin/something', null, route: 'a_plugin_route', withApiAttributes: false);
    }

    public function testAnOperationWithNoResolvablePermissionPassesWhenDenyingIsOff(): void
    {
        $this->listen('/api/v2/admin/custom', null, denyUnresolved: false);

        $this->expectNotToPerformAssertions();
    }

    /**
     * The token endpoint lives under the admin API but is not an API Platform operation, so it
     * carries no resource class. Denying it would make it impossible to obtain the credentials
     * every other operation needs.
     */
    public function testARouteThatIsNotAnApiPlatformOperationIsLeftToTheFirewall(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');

        $this->listen(
            '/api/v2/admin/administrators/token',
            null,
            route: 'sylius_api_admin_authentication_token',
            checker: $checker,
            withApiAttributes: false,
        );
    }

    /**
     * A declaration wins over what the operation itself derives, the same order the HTML side
     * uses. `DELETE /customers/{id}/user` derives "delete a customer" but deletes the shop user
     * account, which is what the admin screen gates it with.
     */
    public function testADeclarationWinsOverWhatTheOperationDerives(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/sylius\.shop_user\.delete/');

        $this->listen(
            '/api/v2/admin/customers/1/user',
            'sylius.customer.delete',
            route: 'sylius_api_admin_customer_delete',
            granted: false,
        );
    }

    private function listen(
        string $path,
        ?string $permission,
        string $route = 'a_route',
        bool $granted = true,
        ?AuthorizationCheckerInterface $checker = null,
        bool $denyUnresolved = true,
        bool $withApiAttributes = true,
    ): void {
        if (null === $checker) {
            $checker = $this->createMock(AuthorizationCheckerInterface::class);
            $checker->method('isGranted')->willReturn($granted);
        }

        $resolver = $this->createMock(ApiOperationPermissionResolverInterface::class);
        $resolver->method('resolve')->willReturn($permission);

        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        if ($withApiAttributes) {
            $request->attributes->set('_api_resource_class', 'Sylius\\Component\\Core\\Model\\Product');
            $request->attributes->set('_api_operation_name', 'an_operation');
        }

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn (): null => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        (new AdminApiAuthorizationListener(
            $checker,
            $resolver,
            [
                'sylius_api_admin_statistics' => 'sylius.statistics.view',
                'sylius_api_admin_customer_delete' => 'sylius.shop_user.delete',
            ],
            ['sylius_api_admin_authentication_token'],
            '/api/v2',
            'admin',
            $denyUnresolved,
        ))->onKernelController($event);
    }
}
