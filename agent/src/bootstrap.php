<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require __DIR__.'/../vendor/autoload.php';

foreach ($autoloader->getClassMap() as $path) {
    require_once $path;
}
