<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Action;

use Odiseo\SyliusRbacPlugin\Message\UpdateAdministrationRole;
use Odiseo\SyliusRbacPlugin\Normalizer\AdministrationRolePermissionNormalizerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UpdateAdministrationRoleAction
{
    public function __construct(
        private MessageBusInterface $bus,
        private AdministrationRolePermissionNormalizerInterface $administrationRolePermissionNormalizer,
        private UrlGeneratorInterface $router
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            /** @var array $administrationRolePermissions */
            $administrationRolePermissions = $request->request->all()['permissions'];

            $normalizedPermissions = $this
                ->administrationRolePermissionNormalizer
                ->normalize($administrationRolePermissions)
            ;

            /** @var string $administrationRoleName */
            $administrationRoleName = $request->request->get('administration_role_name');

            $this->bus->dispatch(new UpdateAdministrationRole(
                $request->attributes->getInt('id'),
                $administrationRoleName,
                $normalizedPermissions
            ));

            $request->getSession()->getFlashBag()->add(
                'success',
                'odiseo_sylius_rbac_plugin.administration_role_successfully_updated'
            );
        } catch (\Exception $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return new RedirectResponse(
            $this->router->generate(
                'odiseo_sylius_rbac_plugin_admin_administration_role_update_view', ['id' => $request->attributes->get('id')]
            )
        );
    }
}
