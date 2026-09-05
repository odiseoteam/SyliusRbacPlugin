<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\TwigHooks;

use Sylius\TwigHooks\Hookable\AbstractHookable;
use Sylius\TwigHooks\Hookable\Metadata\HookableMetadata;
use Sylius\TwigHooks\Hookable\Renderer\SupportableHookableRendererInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Gates a hook behind a permission before Sylius' own renderer ever sees it.
 *
 * A hookable's first render happens inline, as part of rendering the page that embeds it --
 * never through an HTTP route a listener could intercept, and never as the live-action requests
 * `AdminRouteAuthorizationListener` already covers for a component's follow-up interactions. This
 * is the only place that first render can be stopped.
 *
 * A hookable opts in through `config/app/hookable_permissions.yaml`, which
 * `InjectHookablePermissionsPass` turns into this `configuration` key; nothing else about it
 * changes. That is a config value, not a copy of the hook, so it keeps working if Sylius changes
 * the component, props or template behind it.
 *
 * A list grants on *any* of its permissions, for the container of several gated hookables -- an
 * "Actions" dropdown, a button group -- which has no permission of its own and would otherwise
 * open onto nothing once every item inside it is denied.
 *
 * Denying renders nothing rather than throwing: a missing widget is a gap in the layout, not a
 * broken page.
 */
final readonly class PermissionGatedHookableRenderer implements SupportableHookableRendererInterface
{
    private const CONFIGURATION_KEY = 'permission';

    /** @param iterable<SupportableHookableRendererInterface> $delegates the renderers this one hands a granted hookable to */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private iterable $delegates,
    ) {
    }

    public function supports(AbstractHookable $hookable): bool
    {
        return [] !== $this->permissionsOf($hookable);
    }

    public function render(AbstractHookable $hookable, HookableMetadata $metadata): string
    {
        $permissions = $this->permissionsOf($hookable);

        if (!$this->isAnyGranted($permissions)) {
            return '';
        }

        foreach ($this->delegates as $delegate) {
            if ($delegate->supports($hookable)) {
                return $delegate->render($hookable, $metadata);
            }
        }

        throw new \LogicException(sprintf(
            'No renderer supports the "%s" hookable once "%s" is granted.',
            $hookable::class,
            implode('" or "', $permissions),
        ));
    }

    /** @return list<string> */
    private function permissionsOf(AbstractHookable $hookable): array
    {
        $declared = $hookable->configuration[self::CONFIGURATION_KEY] ?? null;

        if (is_string($declared)) {
            return [$declared];
        }

        if (!is_array($declared)) {
            return [];
        }

        return array_values(array_filter($declared, 'is_string'));
    }

    /** @param list<string> $permissions */
    private function isAnyGranted(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->authorizationChecker->isGranted($permission)) {
                return true;
            }
        }

        return false;
    }
}
