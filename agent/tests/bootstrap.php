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

$nightwatchBaseUrl = $_SERVER['NIGHTWATCH_BASE_URL'] ?? null;
$nightwatchToken = $_SERVER['NIGHTWATCH_TOKEN'] ?? null;
$hasRealNightwatchCredentials = ! empty($nightwatchBaseUrl) && ! empty($nightwatchToken);
$fallbackNightwatchBaseUrl = 'https://nightwatch.laravel.com';
$fallbackNightwatchToken = 'fakepkxoLBIOgPE0PZWadR0Ge1zHBh31ATOzXN9bBboZ';

$nightwatchEnvironment = [
    'NIGHTWATCH_BASE_URL' => ! empty($nightwatchBaseUrl) ? $nightwatchBaseUrl : $fallbackNightwatchBaseUrl,
    'NIGHTWATCH_HAS_REAL_CREDENTIALS' => $hasRealNightwatchCredentials ? '1' : '0',
    'NIGHTWATCH_TOKEN' => ! empty($nightwatchToken) ? $nightwatchToken : $fallbackNightwatchToken,
];

foreach ($nightwatchEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
