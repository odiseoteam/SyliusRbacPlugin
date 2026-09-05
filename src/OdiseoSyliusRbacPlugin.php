<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin;

use Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler\CheckAdminUserIsRoleAwarePass;
use Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler\CollectLiveComponentsPass;
use Odiseo\SyliusRbacPlugin\DependencyInjection\Compiler\InjectHookablePermissionsPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class OdiseoSyliusRbacPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CheckAdminUserIsRoleAwarePass());
        $container->addCompilerPass(new CollectLiveComponentsPass());
        $container->addCompilerPass(new InjectHookablePermissionsPass());
    }
}
