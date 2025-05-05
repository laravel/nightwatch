<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration)
    ->ignoreUnknownFunctions(['signature']);
