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
