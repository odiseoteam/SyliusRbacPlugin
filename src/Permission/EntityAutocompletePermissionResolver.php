<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Answers "what permission does this autocomplete request need?".
 *
 * `sylius_admin_entity_autocomplete` is one route shared by every autocomplete field in the
 * admin, so no single permission means the right thing for all of them. Most aliases are fixed
 * to one entity -- `sylius_admin_taxon` always queries Taxon -- and are declared in
 * `$aliasPermissions`. The grid filter aliases are reused across every grid instead, so their
 * target class is not known from the alias; it travels with the request in `extra_options`, the
 * same field the real autocompleter reads to build its query.
 */
final readonly class EntityAutocompletePermissionResolver implements EntityAutocompletePermissionResolverInterface
{
    private const INDEX_OPERATION = 'index';

    /** @param array<string, string> $aliasPermissions alias => permission identifier, for aliases fixed to one entity */
    public function __construct(
        private RegistryInterface $resourceRegistry,
        private array $aliasPermissions = [],
    ) {
    }

    public function resolve(string $alias, Request $request): ?string
    {
        if (isset($this->aliasPermissions[$alias])) {
            return $this->aliasPermissions[$alias];
        }

        $class = $this->targetClass($request);

        if (null === $class) {
            return null;
        }

        try {
            return $this->resourceRegistry->getByClass($class)->getPermissionCode(self::INDEX_OPERATION);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The class a grid filter is querying, read from the same signed `extra_options` the real
     * autocompleter decodes. A tampered value fails that autocompleter's own checksum later in
     * the same request, so nothing is lost by not re-verifying the signature here too.
     */
    private function targetClass(Request $request): ?string
    {
        $encoded = $request->query->get('extra_options');

        if (!is_string($encoded)) {
            return null;
        }

        try {
            $decoded = json_decode(base64_decode($encoded, true) ?: '', true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $class = is_array($decoded) ? ($decoded['class'] ?? null) : null;

        return is_string($class) && '' !== $class ? $class : null;
    }
}
