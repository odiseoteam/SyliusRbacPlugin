<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Permission\Discovery;

/**
 * A route declares `permission: true` but discovery cannot tell which permission it means.
 *
 * Always caught and reported as an unprotected route, never allowed to escape: one odd controller in
 * a third-party plugin must not stop the application from booting.
 */
final class UnmappableRouteException extends \RuntimeException
{
    public static function notAServiceAction(string $controller): self
    {
        return new self(sprintf('controller "%s" is not a "service::action" pair', $controller));
    }

    public static function unknownAction(string $action): self
    {
        return new self(sprintf(
            'action "%s" is not a known resource action, so the permission it checks is unknown',
            $action,
        ));
    }

    public static function notAResourceController(string $service): self
    {
        return new self(sprintf('service "%s" does not look like "{application}.controller.{resource}"', $service));
    }

    public static function malformedIdentifier(string $service, \Throwable $previous): self
    {
        return new self(
            sprintf('service "%s" does not yield a valid permission identifier', $service),
            0,
            $previous,
        );
    }
}
