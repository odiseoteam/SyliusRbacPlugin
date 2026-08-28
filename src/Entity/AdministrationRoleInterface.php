<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Entity;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface AdministrationRoleInterface extends
    ResourceInterface,
    TimestampableInterface
{
    public function getName(): ?string;

    public function setName(?string $name): void;
}
