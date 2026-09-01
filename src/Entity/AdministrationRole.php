<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\TranslatableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;

class AdministrationRole implements AdministrationRoleInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
    }
    use TimestampableTrait;

    protected ?int $id = null;

    /**
     * Stable identity, separate from the display name.
     *
     * The name is translated and an administrator can rename a role at will, so it cannot be
     * what fixtures or configuration point at. Every other named resource in Sylius — channels,
     * payment methods, shipping methods — draws the same line.
     */
    protected ?string $code = null;

    /**
     * Permission patterns, as written: `sylius.product.*`, `*.*.index`.
     *
     * Deliberately not expanded into the operations that exist today. Expanding at save time
     * would freeze the role, so the next Sylius release that adds an operation to products
     * would leave every existing role without it.
     *
     * @var list<string>
     */
    protected array $permissions = [];

    /**
     * Permissions in the pre-v3 format, untouched.
     *
     * **Do not drop this property or its mapping.** It is the only copy of what users coming
     * from 1.x / 2.0 had configured, and `odiseo:rbac:migrate-permissions` reads it
     * through DBAL to produce the patterns above. Without the mapping, the next
     * `doctrine:schema:update` proposes a DROP and takes it with it.
     *
     * No accessors on purpose: interpreting this format is the sole responsibility of
     * `Odiseo\SyliusRbacPlugin\Legacy`, and nothing in the new engine may read it.
     *
     * @var array<array-key, string>
     */
    protected array $legacyPermissions = [];

    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->getAdministrationRoleTranslation()->getName();
    }

    public function setName(?string $name): void
    {
        $this->getAdministrationRoleTranslation()->setName($name);
    }

    /**
     * The patterns as stored, for the form to bind to.
     *
     * `getPermissionPatterns()` stays the API for code that reasons about permissions; this pair
     * exists because a form binds to a property, and the editor's value is the list of rules.
     * Both go through `PermissionPattern`, so nothing malformed reaches the column either way.
     *
     * @return list<string>
     */
    public function getPermissions(): array
    {
        return array_values($this->permissions);
    }

    /** @param list<string> $permissions */
    public function setPermissions(array $permissions): void
    {
        $this->clearPermissionPatterns();

        foreach ($permissions as $pattern) {
            $this->addPermissionPattern(PermissionPattern::fromString($pattern));
        }
    }

    public function getPermissionPatterns(): array
    {
        return array_values(array_map(
            static fn (string $pattern): PermissionPattern => PermissionPattern::fromString($pattern),
            $this->permissions,
        ));
    }

    public function addPermissionPattern(PermissionPattern $pattern): void
    {
        if ($this->hasPermissionPattern($pattern)) {
            return;
        }

        $this->permissions[] = $pattern->toString();
    }

    public function removePermissionPattern(PermissionPattern $pattern): void
    {
        $this->permissions = array_values(
            array_filter($this->permissions, static fn (string $stored): bool => $stored !== $pattern->toString()),
        );
    }

    public function hasPermissionPattern(PermissionPattern $pattern): bool
    {
        return in_array($pattern->toString(), $this->permissions, true);
    }

    public function clearPermissionPatterns(): void
    {
        $this->permissions = [];
    }

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    protected function createTranslation(): TranslationInterface
    {
        return new AdministrationRoleTranslation();
    }

    private function getAdministrationRoleTranslation(): AdministrationRoleTranslationInterface
    {
        /** @var AdministrationRoleTranslationInterface $translation */
        $translation = $this->getTranslation();

        return $translation;
    }
}
