<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Permission\Discovery;

use Odiseo\SyliusRbacPlugin\Permission\Discovery\ChainPermissionDiscoverer;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\DiscoveredPermissions;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use Odiseo\SyliusRbacPlugin\Permission\PermissionRegistry;
use PHPUnit\Framework\TestCase;

final class ChainPermissionDiscovererTest extends TestCase
{
    public function testItPoolsEverythingItsMembersFound(): void
    {
        $chain = new ChainPermissionDiscoverer([
            self::discoverer([self::definition('sylius.product.index')], ['route_a' => 'why a']),
            self::discoverer([self::definition('sylius.order.index')], ['route_b' => 'why b']),
        ]);

        $result = $chain->discover();

        self::assertCount(2, $result->definitions);
        self::assertSame(['route_a' => 'why a', 'route_b' => 'why b'], $result->unprotectedRoutes);
    }

    /**
     * The whole point of pooling rather than picking: discovery says the permission exists, a
     * declaration says what to call it, and the registry merges the two.
     */
    public function testDiscoveryAndDeclarationCombineIntoOnePermission(): void
    {
        $chain = new ChainPermissionDiscoverer([
            self::discoverer([new PermissionDefinition(PermissionIdentifier::fromString('sylius.taxon.index'))]),
            self::discoverer([new PermissionDefinition(
                PermissionIdentifier::fromString('sylius.taxon.index'),
                group: 'catalog',
            )]),
        ]);

        $registry = new PermissionRegistry($chain->discover()->definitions);

        self::assertCount(1, $registry->all());
        self::assertSame('catalog', $registry->get(PermissionIdentifier::fromString('sylius.taxon.index'))->group);
    }

    public function testAnEmptyChainFindsNothing(): void
    {
        $result = (new ChainPermissionDiscoverer())->discover();

        self::assertSame([], $result->definitions);
        self::assertSame([], $result->unprotectedRoutes);
    }

    /**
     * @param list<PermissionDefinition> $definitions
     * @param array<string, string> $unprotectedRoutes
     */
    private static function discoverer(array $definitions, array $unprotectedRoutes = []): PermissionDiscovererInterface
    {
        return new class(new DiscoveredPermissions($definitions, $unprotectedRoutes)) implements PermissionDiscovererInterface {
            public function __construct(private readonly DiscoveredPermissions $result)
            {
            }

            public function discover(): DiscoveredPermissions
            {
                return $this->result;
            }
        };
    }

    private static function definition(string $identifier): PermissionDefinition
    {
        return new PermissionDefinition(PermissionIdentifier::fromString($identifier));
    }
}
