<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require __DIR__.'/../vendor/autoload.php';

foreach ($autoloader->getClassMap() as $class => $path) {
    class_exists($class);
}
