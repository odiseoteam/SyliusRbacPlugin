<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Command;

use Odiseo\SyliusRbacPlugin\Command\DebugPermissionsCommand;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\DiscoveredPermissions;
use Odiseo\SyliusRbacPlugin\Permission\Discovery\PermissionDiscovererInterface;
use Odiseo\SyliusRbacPlugin\Permission\PermissionDefinition;
use Odiseo\SyliusRbacPlugin\Permission\PermissionIdentifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugPermissionsCommandTest extends TestCase
{
    public function testItListsThePermissionsWithTheirPresentationMetadata(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'), group: 'catalog'),
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.index'), group: 'sales'),
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('sylius.order.index', $output);
        self::assertStringContainsString('sylius.product.index', $output);
        self::assertStringContainsString('catalog', $output);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testItCanShowOnlyWhatIsDangerousToGrant(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.impersonation.execute'), dangerous: true),
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index')),
        ], ['--dangerous' => true]);

        self::assertStringContainsString('sylius.impersonation.execute', $tester->getDisplay());
        self::assertStringNotContainsString('sylius.product.index', $tester->getDisplay());
    }

    public function testItCanNarrowToOneSubject(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index')),
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.order.index')),
        ], ['--subject' => 'sylius.product']);

        self::assertStringContainsString('sylius.product.index', $tester->getDisplay());
        self::assertStringNotContainsString('sylius.order.index', $tester->getDisplay());
    }

    public function testItSaysSoWhenNothingMatchesTheFilters(): void
    {
        $tester = $this->runCommand([
            new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index')),
        ], ['--subject' => 'nothing.like_this']);

        self::assertStringContainsString('No permission matches', $tester->getDisplay());
    }

    /**
     * The reason this command exists at all: a route nothing checks has to be
     * put in front of whoever runs it, not hidden behind a flag.
     */
    public function testItPutsUnprotectedRoutesInFrontOfWhoeverRunsIt(): void
    {
        $tester = $this->runCommand(
            [new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))],
            [],
            ['some_plugin_screen' => 'declares no permission, and nothing declares one for it'],
        );

        $output = $tester->getDisplay();

        self::assertStringContainsString('Admin routes nothing checks', $output);
        self::assertStringContainsString('some_plugin_screen', $output);
        self::assertStringContainsString('reachable without any permission check', $output);
    }

    public function testItConfirmsWhenEveryAdminRouteIsAccountedFor(): void
    {
        $tester = $this->runCommand([new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))]);

        self::assertStringContainsString('Every admin route is either covered', $tester->getDisplay());
    }

    public function testPlainListingSucceedsEvenWithUnprotectedRoutesSoScriptsDoNotBreak(): void
    {
        $tester = $this->runCommand([], [], ['some_plugin_screen' => 'declares no permission']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testStrictTurnsAnUnprotectedRouteIntoAFailingBuild(): void
    {
        $tester = $this->runCommand([], ['--strict' => true], ['some_plugin_screen' => 'declares no permission']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testStrictSucceedsWhenEverythingIsCovered(): void
    {
        $tester = $this->runCommand(
            [new PermissionDefinition(PermissionIdentifier::fromString('sylius.product.index'))],
            ['--strict' => true],
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @param list<PermissionDefinition> $definitions
     * @param array<string, mixed> $input
     * @param array<string, string> $unprotectedRoutes
     */
    private function runCommand(array $definitions, array $input = [], array $unprotectedRoutes = []): CommandTester
    {
        $discoverer = new class(new DiscoveredPermissions($definitions, $unprotectedRoutes)) implements PermissionDiscovererInterface {
            public function __construct(private readonly DiscoveredPermissions $result)
            {
            }

            public function discover(): DiscoveredPermissions
            {
                return $this->result;
            }
        };

        $tester = new CommandTester(new DebugPermissionsCommand($discoverer));
        $tester->execute($input);

        return $tester;
    }
}
