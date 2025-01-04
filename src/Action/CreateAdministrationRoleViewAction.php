<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Action;

use Odiseo\SyliusRbacPlugin\Provider\AdminPermissionsProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class CreateAdministrationRoleViewAction
{
    public function __construct(
        private AdminPermissionsProviderInterface $adminPermissionsProvider,
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response(
            $this->twig->render('@OdiseoSyliusRbacPlugin/admin/administration_role/create.html.twig', [
                'permissions' => $this->adminPermissionsProvider->getPossiblePermissions(),
            ]),
        );
    }
}
