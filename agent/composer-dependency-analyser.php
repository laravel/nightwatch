<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    // We have a signalSupport variable that gates any usage of pcntl constants or functions
    ->ignoreErrorsOnExtension('ext-pcntl', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
