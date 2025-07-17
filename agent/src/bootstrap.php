<?php

require __DIR__.'/../vendor/autoload.php';

foreach (require __DIR__.'/../vendor/composer/autoload_classmap.php' as $class => $path) {
    class_exists($class, autoload: true);
}
