<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\DependencyInjection\Compiler;

use Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler\CheckAdminUserIsRoleAwarePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Tests\Odiseo\SyliusRbacPlugin\Unit\Security\RoleAwareAdministrator;

final class CheckAdminUserIsRoleAwarePassTest extends TestCase
{
    /**
     * Forgetting the trait on the application's AdminUser is the likeliest way to misinstall
     * this plugin, and at request time both possible answers are bad: denying locks the owner
     * out of their own store, granting makes the plugin an expensive no-op.
     */
    public function testItRefusesToBootWhenTheAdminUserCannotHoldRoles(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/cannot read roles from/');

        $this->process(InMemoryUser::class);
    }

    public function testItSaysHowToFixIt(): void
    {
        try {
            $this->process(InMemoryUser::class);
        } catch (\LogicException $exception) {
            self::assertStringContainsString('AdministrationRoleAwareInterface', $exception->getMessage());
            self::assertStringContainsString('AdministrationRoleAwareTrait', $exception->getMessage());

            return;
        }

        self::fail('expected the pass to complain');
    }

    public function testItAcceptsAnAdminUserThatCanHoldRoles(): void
    {
        $this->process(RoleAwareAdministrator::class);

        $this->expectNotToPerformAssertions();
    }

    /** Sylius not installed at all is not this pass's problem to report. */
    public function testItStaysQuietWhenThereIsNoAdminUserConfigured(): void
    {
        (new CheckAdminUserIsRoleAwarePass())->process(new ContainerBuilder());

        $this->expectNotToPerformAssertions();
    }

    public function testItStaysQuietWhenTheConfiguredClassDoesNotExist(): void
    {
        $this->process('App\\Entity\\NotYetGenerated');

        $this->expectNotToPerformAssertions();
    }

    private function process(string $adminUserClass): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sylius.model.admin_user.class', $adminUserClass);

        (new CheckAdminUserIsRoleAwarePass())->process($container);
    }
}
