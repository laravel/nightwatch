<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration)
    ->enableAnalysisOfUnusedDevDependencies()
    ->ignoreUnknownFunctions(['signature']);
