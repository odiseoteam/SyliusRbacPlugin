<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Action;

use Sylius\Component\Resource\Repository\RepositoryInterface;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleInterface;
use Odiseo\SyliusRbacPlugin\Provider\AdminPermissionsProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class UpdateAdministrationRoleViewAction
{
    public function __construct(
        private AdminPermissionsProviderInterface $adminPermissionsProvider,
        private RepositoryInterface $administrationRoleRepository,
        private Environment $twig,
        private UrlGeneratorInterface $router
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var AdministrationRoleInterface|null $administrationRole */
        $administrationRole = $this->administrationRoleRepository->find($request->attributes->get('id'));

        if (null === $administrationRole) {
            $request->getSession()->getFlashBag()->add('error', [
                'message' => 'odiseo_sylius_rbac_plugin.administration_role_not_found',
                'parameters' => ['%administration_role_id%' => $request->attributes->get('id')],
            ]);

            return new RedirectResponse($this->router->generate('odiseo_sylius_rbac_plugin_admin_administration_role_index'));
        }

        return new Response(
            $this->twig->render('@OdiseoSyliusRbacPlugin/Admin/AdministrationRole/update.html.twig', [
                'administration_role' => $administrationRole,
                'permissions' => $this->adminPermissionsProvider->getPossiblePermissions(),
                'rolePermissions' => $administrationRole->getPermissions(),
            ])
        );
    }
}
