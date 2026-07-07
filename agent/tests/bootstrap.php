<?php

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

if (! ($_SERVER['CI'] ?? false)) {
    try {
        Dotenv::createImmutable(__DIR__.'/../', '.env.testing')->load();
    } catch (InvalidPathException $e) {
        echo 'You have not configured your local `.env.testing` file. Please run `cp .env.example .env.testing` and configure the variables as needed.';

        exit(1);
    }
}

$hasRealNightwatchCredentials = ! empty($_SERVER['NIGHTWATCH_BASE_URL']) && ! empty($_SERVER['NIGHTWATCH_TOKEN']);
$fallbackNightwatchBaseUrl = 'https://nightwatch.laravel.com';
$fallbackNightwatchToken = 'fakepkxoLBIOgPE0PZWadR0Ge1zHBh31ATOzXN9bBboZ';

$nightwatchEnvironment = [
    'NIGHTWATCH_BASE_URL' => ! empty($_SERVER['NIGHTWATCH_BASE_URL']) ? $_SERVER['NIGHTWATCH_BASE_URL'] : $fallbackNightwatchBaseUrl,
    'NIGHTWATCH_HAS_REAL_CREDENTIALS' => $hasRealNightwatchCredentials ? '1' : '0',
    'NIGHTWATCH_TOKEN' => ! empty($_SERVER['NIGHTWATCH_TOKEN']) ? $_SERVER['NIGHTWATCH_TOKEN'] : $fallbackNightwatchToken,
];

foreach ($nightwatchEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
