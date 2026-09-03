<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * Shadow dependencies are ignored on purpose: like the sibling Sylius plugins, this one requires
 * `sylius/sylius` as an umbrella and uses whatever Symfony/Sylius components that pulls in,
 * rather than re-declaring each one directly. Flagging those would be the tool objecting to a
 * deliberate convention, not finding a real unused dependency.
 */
return (new Configuration())
    ->ignoreErrors([ErrorType::SHADOW_DEPENDENCY])
;
