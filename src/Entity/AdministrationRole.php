<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Sylius\Component\Resource\Model\TimestampableTrait;

class AdministrationRole implements AdministrationRoleInterface
{
    use TimestampableTrait;

    protected ?int $id = null;

    protected ?string $name = null;

    /**
     * Pre-v3 engine permissions, serialized to JSON.
     *
     * **Do not drop this property or its mapping in `config/doctrine/AdministrationRole.orm.xml`.**
     * It holds down the `permissions` column, where the data of users coming from 1.x / 2.0
     * still lives. Without the mapping, the next `doctrine:schema:update` proposes a DROP and
     * takes that data with it before it can be migrated.
     *
     * Accessors are omitted on purpose: nothing in the new engine reads it, and the data
     * migration command (PR 6) reads the JSON through DBAL rather than through this entity, so
     * it does not depend on whatever shape the entity takes after PR 5. Interpreting this
     * format is the sole responsibility of `Odiseo\SyliusRbacPlugin\Legacy`.
     *
     * @var array<array-key, string>
     */
    protected array $permissions = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
