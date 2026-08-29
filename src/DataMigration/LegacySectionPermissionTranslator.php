<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\DataMigration;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\RoutePermissionResolver;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\UnmappableRouteException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Sylius\Resource\ResourceActions;
use Symfony\Component\Routing\RouterInterface;

/**
 * Translates one pre-v3 section grant into v3 permission patterns.
 *
 * The old engine resolved a section by matching the route name against a list of prefixes, and
 * derived read/write from the HTTP method. The prefixes still live in the `sylius_sections`
 * configuration, so the section-to-permission table is derived from what the installation is
 * actually running rather than hard-coded here. That covers `custom_sections` as well.
 *
 * The two rules are deliberately not symmetric:
 *
 * - **write** becomes `{package}.{subject}.*` for every subject the section reached. The old
 *   model had no notion of "edit but not delete", so narrowing would remove access an
 *   administrator has today.
 * - **read** becomes only `index` and `show`, never `*`. A legacy read grant opened the edit
 *   form (a GET) but could not submit it (a PUT). Both halves are the same `update` permission
 *   now, so a wildcard would turn every read-only administrator into a writer.
 */
final readonly class LegacySectionPermissionTranslator
{
    private const READ_OPERATIONS = [ResourceActions::INDEX, ResourceActions::SHOW];

    /**
     * The order the old engine tested the sections in, reproduced exactly. Custom sections came
     * last.
     *
     * Prefixes overlap: `sylius_admin_product` (catalog) is a prefix of
     * `sylius_admin_product_review_index` (marketing). The old engine stopped at the first
     * match, so every route belonged to exactly one section. Matching every section that fits
     * would grant access nobody was given.
     */
    private const SECTION_PRECEDENCE = [
        'configuration',
        'customers_management',
        'marketing_management',
        'sales_management',
        'catalog_management',
    ];

    /** @var array<string, list<string>> */
    private array $sectionRoutePrefixes;

    /**
     * @param array<string, list<string>> $sectionRoutePrefixes section name => route name
     *        prefixes, as configured under `odiseo_sylius_rbac.sylius_sections` and
     *        `custom_sections` before v3
     */
    public function __construct(
        private RouterInterface $router,
        private RoutePermissionResolver $resolver,
        array $sectionRoutePrefixes,
    ) {
        $this->sectionRoutePrefixes = self::inPrecedenceOrder($sectionRoutePrefixes);
    }

    public function knowsSection(string $section): bool
    {
        return isset($this->sectionRoutePrefixes[$section]);
    }

    /**
     * @return list<string> permission patterns, sorted
     */
    public function translate(string $section, bool $writeAllowed): array
    {
        $patterns = [];

        foreach ($this->identifiersOf($section) as $identifier) {
            if ($writeAllowed) {
                $patterns[] = sprintf('%s.%s.*', $identifier->package, $identifier->subject);

                continue;
            }

            if (in_array($identifier->operation, self::READ_OPERATIONS, true)) {
                $patterns[] = $identifier->toString();
            }
        }

        $patterns = array_values(array_unique($patterns));
        sort($patterns);

        return $patterns;
    }

    /**
     * @return list<PermissionIdentifier>
     */
    private function identifiersOf(string $section): array
    {
        $identifiers = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            if ($section !== $this->sectionOf((string) $name)) {
                continue;
            }

            if (!$this->resolver->enforcesPermission($route)) {
                continue;
            }

            try {
                $identifiers[] = $this->resolver->resolve($route);
            } catch (UnmappableRouteException) {
                /**
                 * A route the registry cannot name cannot be granted either. It is not lost:
                 * `odiseo:rbac:debug` reports it as unprotected, which is where that problem
                 * belongs. Failing the migration over it would block the upgrade on something
                 * the upgrade cannot fix.
                 */
                continue;
            }
        }

        return $identifiers;
    }

    private function sectionOf(string $routeName): ?string
    {
        foreach ($this->sectionRoutePrefixes as $section => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return $section;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, list<string>> $sectionRoutePrefixes
     *
     * @return array<string, list<string>>
     */
    private static function inPrecedenceOrder(array $sectionRoutePrefixes): array
    {
        $ordered = [];

        foreach (self::SECTION_PRECEDENCE as $section) {
            if (isset($sectionRoutePrefixes[$section])) {
                $ordered[$section] = $sectionRoutePrefixes[$section];
            }
        }

        foreach ($sectionRoutePrefixes as $section => $prefixes) {
            $ordered[$section] ??= $prefixes;
        }

        return $ordered;
    }
}
