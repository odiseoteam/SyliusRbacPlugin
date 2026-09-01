<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Attribute;

/**
 * Declares a permission on the controller that needs it.
 *
 * This is **not** how a permission comes to exist: discovery already finds every permission the
 * routes enforce. It is how one gets a readable name and a group in the tree. A controller
 * without the attribute is still covered, under a machine-made name.
 *
 * Repeatable, because one action can require more than one permission.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class RbacPermission
{
    /**
     * @param string $identifier `{package}.{subject}.{operation}`, e.g. `sylius.impersonation.execute`
     * @param string|null $label translation key shown in the permission tree
     * @param string|null $group heading to file it under; presentation only, never part of identity
     */
    public function __construct(
        public string $identifier,
        public ?string $label = null,
        public ?string $group = null,
    ) {
    }
}
