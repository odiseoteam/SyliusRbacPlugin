<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Security;

use Odiseo\SyliusRbacPlugin\Security\ResourceAuthorizationChecker;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ResourceAuthorizationCheckerTest extends TestCase
{
    public function testItAsksTheVoterForThePermissionTheControllerNamed(): void
    {
        $asked = $this->ask('sylius.product.update', stateMachineTransition: null);

        self::assertSame('sylius.product.update', $asked);
    }

    /**
     * Sylius applies a workflow transition through `updateAction`, so cancelling an order asks
     * for `sylius.product.update` — the same permission as editing one. Substituting the
     * transition is what makes "may cancel orders" grantable without "may edit orders".
     */
    public function testATransitionAsksForItsOwnPermission(): void
    {
        $asked = $this->ask('sylius.order.update', stateMachineTransition: 'cancel');

        self::assertSame('sylius.order.cancel', $asked);
    }

    /**
     * An application using its own permission strings, or a transition name that is not a valid
     * identifier segment. Falling back leaves Sylius' behaviour rather than denying over a
     * naming detail.
     */
    public function testItFallsBackWhenTheSubstitutionCannotBeBuilt(): void
    {
        self::assertSame(
            'ROLE_SOMETHING',
            $this->ask('ROLE_SOMETHING', stateMachineTransition: 'cancel'),
        );

        self::assertSame(
            'sylius.order.update',
            $this->ask('sylius.order.update', stateMachineTransition: 'Not A Segment'),
        );
    }

    private function ask(string $permission, ?string $stateMachineTransition): string
    {
        $asked = null;

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static function (mixed $attribute) use (&$asked): bool {
                $asked = $attribute;

                return true;
            },
        );

        $configuration = $this->createMock(RequestConfiguration::class);
        $configuration->method('hasStateMachine')->willReturn(null !== $stateMachineTransition);
        $configuration->method('getStateMachineTransition')->willReturn($stateMachineTransition);

        (new ResourceAuthorizationChecker($checker))->isGranted($configuration, $permission);

        self::assertIsString($asked);

        return $asked;
    }
}
