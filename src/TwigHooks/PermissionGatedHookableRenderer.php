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
 * A hook opts in with `configuration: {permission: sylius.subject.operation}`; nothing else about
 * it changes. That is a config value, not a copy of the hook, so it keeps working if Sylius
 * changes the component, props or template behind it -- see the dashboard widgets in
 * `config/app/twig_hooks/admin/dashboard.yaml`.
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
        return is_string($hookable->configuration[self::CONFIGURATION_KEY] ?? null);
    }

    public function render(AbstractHookable $hookable, HookableMetadata $metadata): string
    {
        /** @var string $permission */
        $permission = $hookable->configuration[self::CONFIGURATION_KEY];

        if (!$this->authorizationChecker->isGranted($permission)) {
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
            $permission,
        ));
    }
}
