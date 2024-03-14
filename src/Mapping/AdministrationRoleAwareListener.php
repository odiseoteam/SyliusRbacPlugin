<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Mapping;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Odiseo\SyliusRbacPlugin\Entity\AdministrationRoleAwareInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Metadata\RegistryInterface;

final class AdministrationRoleAwareListener implements EventSubscriber
{
    public function __construct(
        private RegistryInterface $resourceMetadataRegistry,
        private string $administrationRoleClass,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $reflection = $classMetadata->reflClass;

        /**
         * @phpstan-ignore-next-line
         */
        if ($reflection === null || $reflection->isAbstract()) {
            return;
        }

        if (
            $reflection->implementsInterface(AdminUserInterface::class) &&
            $reflection->implementsInterface(AdministrationRoleAwareInterface::class)
        ) {
            $this->mapAdministrationRoleAware($classMetadata);
        }
    }

    private function mapAdministrationRoleAware(ClassMetadata $metadata): void
    {
        try {
            $administrationRoleMetadata = $this->resourceMetadataRegistry->getByClass($this->administrationRoleClass);
        } catch (\InvalidArgumentException $exception) {
            return;
        }

        $metadata->mapManyToOne([
            'fieldName' => 'administrationRole',
            'targetEntity' => $administrationRoleMetadata->getClass('model'),
            'joinColumns' => [
                [
                    'name' => 'administration_role_id',
                    'referencedColumnName' => 'id',
                    'nullable' => false,
                ],
            ],
        ]);
    }
}
