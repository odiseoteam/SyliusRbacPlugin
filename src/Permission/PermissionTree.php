<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Knp\Menu\ItemInterface;
use Odiseo\SyliusRbacPlugin\Menu\UnfilteredMenuProviderInterface;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\UnmappableRouteException;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * The permission vocabulary arranged for a person to read.
 *
 * Groups come from the admin menu rather than from the identifier. That looks like the
 * "domain = menu section" idea the identifier format deliberately rejects, and it is the
 * opposite: the group is presentation only and never reaches the stored pattern, so the menu
 * can be reorganised without invalidating a single role. What it buys is that permissions are
 * filed where the administrator already looks for the screens they govern.
 *
 * Subjects reachable only from inside another screen — coupons, product taxons — have no menu
 * entry of their own. They are nested under the subject whose screen reaches them, which is
 * also the section they are filed in. Whatever is left over is collected under its own heading
 * rather than hidden.
 */
final class PermissionTree implements PermissionTreeInterface
{
    /**
     * The operations every group's table starts with, in the order they are read in.
     *
     * `show` is here even though only six subjects declare it: it is the sixth CRUD operation,
     * not an exotic one, and a row without it says something true — that resource has no detail
     * screen. Each group adds its own operations after these; see `PermissionGroup::columns()`.
     */
    public const COLUMNS = ['index', 'show', 'create', 'update', 'delete', 'bulk_delete'];

    /** What "read only" means when granted in bulk. */
    public const READ_COLUMNS = ['index', 'show'];

    private const UNGROUPED = 'not_on_the_menu';

    /** @var array<string, PermissionGroup>|null */
    private ?array $tree = null;

    /** @param array<string, string> $subjectParents subject key => the subject it is reached from */
    public function __construct(
        private readonly PermissionRegistryInterface $registry,
        private readonly RouterInterface $router,
        private readonly RoutePermissionResolver $routes,
        private readonly UnfilteredMenuProviderInterface $menuProvider,
        private readonly RegistryInterface $resourceRegistry,
        private readonly array $subjectParents = [],
    ) {
    }

    /** @return list<PermissionGroup> */
    public function groups(): array
    {
        return array_values($this->tree ??= $this->build());
    }

    /** @return array<string, PermissionGroup> */
    private function build(): array
    {
        [$fromMenu, $menuLabels, $menuOrder, $sectionOrder] = $this->fromMenu();
        $definitions = $this->registry->all();

        $subjectKeys = [];

        foreach ($definitions as $definition) {
            $subjectKey = self::subjectKeyOf($definition);
            $subjectKeys[$subjectKey] = true;
        }

        $subjectKeys = array_keys($subjectKeys);
        $parents = $this->parents($subjectKeys, $fromMenu);
        $labels = [];

        foreach ($subjectKeys as $subjectKey) {
            $labels[$subjectKey] = $menuLabels[$subjectKey] ?? $this->fallbackLabel($subjectKey);
        }

        foreach ($parents as $subjectKey => $parent) {
            $labels[$subjectKey] = self::withoutParentPrefix($labels[$subjectKey], $parent);
        }

        /**
         * The group is decided once per subject, not per permission.
         *
         * Operations of one subject can disagree: coupon CRUD is reached from inside the
         * promotion screen and so has no menu entry, while `promotion_coupon.generate` is
         * declared under marketing. Deciding per permission split that subject across two
         * sections of the tree.
         *
         * The menu wins over a declaration, because only the menu covers every subject and
         * mixing the two sources would file half a section under each name.
         */
        $groupOfSubject = [];

        foreach ($definitions as $definition) {
            $subjectKey = self::subjectKeyOf($definition);
            $groupOfSubject[$subjectKey] ??= $fromMenu[$subjectKey] ?? $definition->group;
        }

        // A nested subject is filed wherever its parent is: it is read as part of that screen.
        foreach ($parents as $subjectKey => $parent) {
            $groupOfSubject[$subjectKey] = $groupOfSubject[$parent] ?? $groupOfSubject[$subjectKey] ?? null;
        }

        $groups = [];

        foreach ($definitions as $definition) {
            $subjectKey = self::subjectKeyOf($definition);
            $groupName = self::canonical($groupOfSubject[$subjectKey] ?? self::UNGROUPED);

            $groups[$groupName] ??= new PermissionGroup($groupName, $menuOrder);
            $groups[$groupName]->add($definition, $labels[$subjectKey], $parents[$subjectKey] ?? null);
        }

        /**
         * Groups follow the order their section is read in on the admin menu. One the menu never
         * mentions sorts after the ones it does, alphabetically among itself; the leftovers last.
         */
        uasort($groups, static fn (PermissionGroup $a, PermissionGroup $b): int => [
            $a->name === self::UNGROUPED, $sectionOrder[$a->name] ?? \PHP_INT_MAX, $a->name,
        ] <=> [$b->name === self::UNGROUPED, $sectionOrder[$b->name] ?? \PHP_INT_MAX, $b->name]);

        return $groups;
    }

    private static function subjectKeyOf(PermissionDefinition $definition): string
    {
        return $definition->identifier->package . '.' . $definition->identifier->subject;
    }

    /**
     * Which subject each one is reached from, for the subjects with no screen of their own.
     *
     * Derived from the identifier wherever it says so: `sylius.product_taxon` extends
     * `sylius.product`, so it belongs to the product screen without anyone declaring it, and the
     * longest match wins so a deeper name does not attach itself to a shorter ancestor. The
     * declared map covers what the identifier cannot express, such as the shop user account
     * edited from the customer screen.
     *
     * Only subjects absent from the menu are considered: one with its own entry is a screen in
     * its own right, whatever its name looks like.
     *
     * @param list<string> $subjectKeys
     * @param array<string, string> $onMenu
     *
     * @return array<string, string> subject key => parent subject key
     */
    private function parents(array $subjectKeys, array $onMenu): array
    {
        $parents = [];

        foreach ($subjectKeys as $subjectKey) {
            if (isset($onMenu[$subjectKey])) {
                continue;
            }

            $declared = $this->subjectParents[$subjectKey] ?? null;

            if (null !== $declared) {
                if (in_array($declared, $subjectKeys, true)) {
                    $parents[$subjectKey] = $declared;
                }

                continue;
            }

            $best = null;

            foreach ($subjectKeys as $candidate) {
                if (!str_starts_with($subjectKey, $candidate . '_')) {
                    continue;
                }

                if (null === $best || strlen($candidate) > strlen($best)) {
                    $best = $candidate;
                }
            }

            if (null !== $best) {
                $parents[$subjectKey] = $best;
            }
        }

        return $parents;
    }

    /**
     * Drops the parent's own name from a nested subject's label: the row already sits under it,
     * so "Product taxa" under "Products" says the same word twice. Left untouched when the label
     * does not start with it, which is the case for a parent that was declared rather than
     * derived from the identifier.
     */
    private static function withoutParentPrefix(string $label, string $parentKey): string
    {
        $parent = str_replace('_', ' ', explode('.', $parentKey)[1] ?? '') . ' ';

        if ('' === trim($parent) || !str_starts_with(strtolower($label), strtolower($parent))) {
            return $label;
        }

        return ucfirst(substr($label, strlen($parent)));
    }

    /**
     * A name a shop manager recognises, for subjects the menu never links to.
     *
     * Sylius humanizes the resource name — `product_taxon` becomes "Product taxa" — which is not
     * translated but is still words rather than an identifier. The raw subject is the last
     * resort, for a permission whose middle segment names a capability with no resource behind
     * it, like `impersonation`.
     */
    private function fallbackLabel(string $subjectKey): string
    {
        foreach ($this->resourceRegistry->getAll() as $metadata) {
            if ($metadata->getApplicationName() . '.' . $metadata->getName() === $subjectKey) {
                return self::humanize($metadata->getPluralName());
            }
        }

        return self::humanize(explode('.', $subjectKey)[1] ?? $subjectKey);
    }

    private static function humanize(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }

    /**
     * @return array{array<string, string>, array<string, string>, array<string, int>, array<string, int>}
     *         menu section, the label of the entry itself — the word the administrator already
     *         reads in the navigation — the position of the entry, and the position of its section
     */
    private function fromMenu(): array
    {
        $menu = $this->menuProvider->menu();

        if (null === $menu) {
            return [[], [], [], []];
        }

        $groups = [];
        $labels = [];
        $order = [];
        $sectionOrder = [];

        foreach ($menu->getChildren() as $section) {
            $sectionLabel = $section->getLabel() ?: $section->getName();

            /**
             * Taken from the section rather than from one of its subjects: a section whose own
             * route needs no permission — Dashboard — has no subject to derive a position from.
             */
            $sectionOrder[self::canonical($sectionLabel)] ??= count($sectionOrder);

            $this->walk($section, function (string $uri, string $itemLabel) use (&$groups, &$labels, &$order, $sectionLabel): void {
                $subject = $this->subjectOf($uri);

                if (null === $subject) {
                    return;
                }

                $groups[$subject] ??= $sectionLabel;
                $labels[$subject] ??= $itemLabel;
                $order[$subject] ??= count($order);
            });
        }

        return [$groups, $labels, $order, $sectionOrder];
    }

    /**
     * Reduces a group name to one token, so the menu's label and a declaration's own name land
     * in the same section instead of two that read the same.
     */
    private static function canonical(string $name): string
    {
        if (1 === preg_match('/^sylius\.menu\.admin\.main\.([a-z_]+)\.header$/', $name, $matches)) {
            return $matches[1];
        }

        if (1 === preg_match('/^sylius\.ui\.([a-z_]+)$/', $name, $matches)) {
            return $matches[1];
        }

        return strtolower($name);
    }

    private function walk(ItemInterface $item, callable $onUri): void
    {
        $uri = $item->getUri();

        if (is_string($uri) && str_starts_with($uri, '/')) {
            $onUri($uri, $item->getLabel() ?: $item->getName());
        }

        foreach ($item->getChildren() as $child) {
            $this->walk($child, $onUri);
        }
    }

    /**
     * Which subject a menu entry leads to, asked of the same resolver the registry and the data
     * migration use. An entry that leads nowhere the registry knows about — an external link, a
     * plain controller — simply has no subject, and its section is left to the declaration.
     */
    private function subjectOf(string $uri): ?string
    {
        try {
            $routeName = $this->router->match((string) (parse_url($uri, \PHP_URL_PATH) ?: $uri))['_route'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        $route = is_string($routeName) ? $this->router->getRouteCollection()->get($routeName) : null;

        if (null === $route) {
            return null;
        }

        try {
            $identifier = $this->routes->resolve($route);
        } catch (UnmappableRouteException) {
            return null;
        }

        return $identifier->package . '.' . $identifier->subject;
    }
}
