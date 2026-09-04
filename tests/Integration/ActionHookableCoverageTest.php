<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Integration;

use Sylius\TwigHooks\Hookable\AbstractHookable;
use Sylius\TwigHooks\Hookable\DisabledHookable;
use Sylius\TwigHooks\Registry\HookablesRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The same rot check as `PermissionDeclarationsTest`, one surface further out.
 *
 * The action buttons are gated by a YAML file keyed on Sylius' hook names, which is exactly as
 * coupled to somebody else's naming as `route_permissions` is to their routes. A button Sylius
 * adds is a button that invites a 403; a hook Sylius renames leaves an entry gating nothing.
 */
final class ActionHookableCoverageTest extends KernelTestCase
{
    /**
     * Hooks whose name says the hookables under them are things to *do*, which is where a button
     * that should be gated would appear.
     */
    private const ACTION_HOOK_MARKER = '.actions';

    public function testEveryActionButtonIsEitherGatedOrDeclaredUngated(): void
    {
        /** @var array<string, list<string>> $ungated */
        $ungated = self::getContainer()->getParameter('odiseo_rbac.ungated_action_hookables');
        $found = 0;

        foreach ($this->actionHookables() as $hook => $hookables) {
            foreach ($hookables as $name => $hookable) {
                ++$found;

                if (null !== ($hookable->configuration['permission'] ?? null)) {
                    continue;
                }

                self::assertContains(
                    $name,
                    $ungated[$hook] ?? [],
                    sprintf(
                        'Hookable "%s" under hook "%s" renders without any permission. Give it one in ' .
                        'config/app/twig_hooks/admin/actions.yaml, or list it under ' .
                        '"odiseo_sylius_rbac.ungated_action_hookables" if it is not an action button.',
                        $name,
                        $hook,
                    ),
                );
            }
        }

        self::assertGreaterThan(0, $found, 'no action hookable was found at all');
    }

    /**
     * The other half: an entry that gates nothing. A hook Sylius renames, or a hookable it stops
     * shipping, leaves a line in the YAML that reads as protection and is not.
     */
    public function testNoDeclarationGatesAHookableThatIsNoLongerThere(): void
    {
        $hookables = $this->allHookables();

        /** @var array<string, list<string>> $ungated */
        $ungated = self::getContainer()->getParameter('odiseo_rbac.ungated_action_hookables');

        foreach ($ungated as $hook => $names) {
            foreach ($names as $name) {
                self::assertArrayHasKey(
                    $name,
                    $hookables[$hook] ?? [],
                    sprintf('"%s" is declared ungated under hook "%s", which no longer exists.', $name, $hook),
                );
            }
        }
    }

    /** @return array<string, array<string, AbstractHookable>> */
    private function actionHookables(): array
    {
        $hookables = [];

        foreach ($this->allHookables() as $hook => $hookablesOfHook) {
            if (!str_starts_with($hook, 'sylius_admin.') || !str_contains($hook, self::ACTION_HOOK_MARKER)) {
                continue;
            }

            foreach ($hookablesOfHook as $name => $hookable) {
                // Sylius disables the hookables it replaced rather than removing them -- the
                // order screen keeps a disabled `cancel` next to the one in the dropdown. They
                // never render, so gating them would gate nothing.
                if (!$hookable instanceof DisabledHookable) {
                    $hookables[$hook][$name] = $hookable;
                }
            }
        }

        return $hookables;
    }

    /**
     * `HookablesRegistry` only answers "what is under these hooks?", so the hook names have to
     * come from somewhere. There is no public way to list them, and the point of this test is to
     * notice hooks nobody wrote down -- so it reads the registry's own index. If Sylius changes
     * that class this test breaks while the plugin is fine, which is the trade being made.
     *
     * @return array<string, array<string, AbstractHookable>>
     */
    private function allHookables(): array
    {
        /** @var HookablesRegistry $registry */
        $registry = self::getContainer()->get('sylius_twig_hooks.registry.hookables');

        /** @var array<string, array<string, AbstractHookable>> $hookables */
        $hookables = (new \ReflectionProperty($registry, 'hookables'))->getValue($registry);

        return $hookables;
    }
}
