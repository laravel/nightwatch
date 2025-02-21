<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->ignoreErrorsOnExtension('ext-zlib', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackageAndPath('spatie/laravel-ignition', __DIR__.'/src/Sensors/ExceptionSensor.php', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackageAndPath('spatie/laravel-ignition', __DIR__.'/src/Location.php', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
