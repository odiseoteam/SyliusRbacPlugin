<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Sylius\Component\Resource\Model\AbstractTranslation;

class AdministrationRoleTranslation extends AbstractTranslation implements AdministrationRoleTranslationInterface
{
    protected ?int $id = null;

    protected ?string $name = null;

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
