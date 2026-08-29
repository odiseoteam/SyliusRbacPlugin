<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission;

/**
 * A permission the application knows about, plus how to present it.
 *
 * Identity is the identifier and nothing else. `label` and `group` are presentation, and are
 * kept out of the identifier so the tree can be reorganised without invalidating stored roles.
 * `dangerous` marks capabilities that warrant a warning when granted, such as impersonation.
 */
final readonly class PermissionDefinition
{
    public function __construct(
        public PermissionIdentifier $identifier,
        public ?string $label = null,
        public ?string $group = null,
        public bool $dangerous = false,
    ) {
    }

    /**
     * Combines two descriptions of the same permission, with `$other` winning wherever it has an
     * opinion. This is how a declaration enriches what discovery already found.
     */
    public function mergedWith(self $other): self
    {
        if (!$this->identifier->equals($other->identifier)) {
            throw new \LogicException(sprintf(
                'Cannot merge permission "%s" with "%s": merging only makes sense for the same identifier.',
                $this->identifier->toString(),
                $other->identifier->toString(),
            ));
        }

        return new self(
            $this->identifier,
            $other->label ?? $this->label,
            $other->group ?? $this->group,
            // Once something has been flagged dangerous, a later description cannot un-flag it.
            $this->dangerous || $other->dangerous,
        );
    }
}
