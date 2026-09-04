<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Form\Type;

use Odiseo\SyliusRbacPlugin\Permission\Exception\InvalidPermissionSyntaxException;
use Odiseo\SyliusRbacPlugin\Permission\PermissionPattern;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Carries the role's permission patterns as one field.
 *
 * A hidden field holding a newline-separated list, with the tree rendered around it. The tree is
 * an editor for this value, not a collection of checkboxes bound to the form: what a role stores
 * is a set of rules, and half of them — `sylius.product.*` — have no single checkbox to bind to.
 */
final class PermissionPatternsType extends AbstractType
{
    private const SEPARATOR = "\n";

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            /** @param list<string>|null $patterns */
            static fn (?array $patterns): string => implode(self::SEPARATOR, $patterns ?? []),
            static function (?string $value): array {
                $patterns = array_filter(array_map('trim', explode(self::SEPARATOR, (string) $value)));
                $valid = [];

                foreach ($patterns as $pattern) {
                    try {
                        $valid[] = PermissionPattern::fromString($pattern)->toString();
                    } catch (InvalidPermissionSyntaxException) {
                        /**
                         * Dropped rather than rejected. The value is produced by the editor, so
                         * anything malformed reaching here is a tampered request, and storing a
                         * pattern nothing can match would grant nothing while looking like it
                         * grants something.
                         */
                        continue;
                    }
                }

                return array_values(array_unique($valid));
            },
        ));
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'odiseo_rbac_permission_patterns';
    }
}
