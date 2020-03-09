<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Creator;

use Prooph\Common\Messaging\Command;
use Odiseo\SyliusRbacPlugin\Command\CreateAdministrationRole;
use Odiseo\SyliusRbacPlugin\Normalizer\AdministrationRolePermissionNormalizerInterface;
use Symfony\Component\HttpFoundation\Request;

final class CreateAdministrationRoleCommandCreator implements CommandCreatorInterface
{
    /** @var AdministrationRolePermissionNormalizerInterface */
    private $administrationRolePermissionNormalizer;

    public function __construct(AdministrationRolePermissionNormalizerInterface $administrationRolePermissionNormalizer)
    {
        $this->administrationRolePermissionNormalizer = $administrationRolePermissionNormalizer;
    }

    public function fromRequest(Request $request): Command
    {
        $normalizedPermissions = $this
            ->administrationRolePermissionNormalizer
            ->normalize($request->request->get('permissions', []))
        ;

        $command = new CreateAdministrationRole(
            $request->request->get('administration_role_name'),
            $normalizedPermissions
        );

        return $command;
    }
}
