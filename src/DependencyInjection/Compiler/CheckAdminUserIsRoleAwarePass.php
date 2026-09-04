<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler;

use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Refuses to boot when the application's admin user cannot hold roles.
 *
 * Applying `AdministrationRoleAwareTrait` to the application's `AdminUser` is a manual install
 * step until a Flex recipe automates it, and forgetting it is the likeliest way to
 * get this plugin wrong. Both runtime behaviours available to the voter at that point are bad:
 * denying locks the owner out of their own store with only a log line to explain it, and
 * granting turns a security plugin into an expensive no-op.
 *
 * So the question is answered before any request exists, where the fix can be spelled out.
 */
final class CheckAdminUserIsRoleAwarePass implements CompilerPassInterface
{
    private const ADMIN_USER_CLASS_PARAMETER = 'sylius.model.admin_user.class';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::ADMIN_USER_CLASS_PARAMETER)) {
            return;
        }

        $adminUserClass = $container->getParameter(self::ADMIN_USER_CLASS_PARAMETER);

        if (!is_string($adminUserClass) || !class_exists($adminUserClass)) {
            return;
        }

        if (is_a($adminUserClass, AdministrationRoleAwareInterface::class, true)) {
            return;
        }

        throw new \LogicException(sprintf(
            'The administration role engine cannot read roles from "%s". Make that class implement "%s" and use "%s", then register it as the "admin_user" resource model. Without it no administrator can hold a role, and every permission check would deny.',
            $adminUserClass,
            AdministrationRoleAwareInterface::class,
            AdministrationRoleAwareTrait::class,
        ));
    }
}
