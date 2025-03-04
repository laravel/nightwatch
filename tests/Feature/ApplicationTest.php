<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

it('can cache the config', function () {
    $basePath = app()->basePath();

    $result = Artisan::call('config:cache');
    expect($result)->toBe(0);

    app()->setBasePath($basePath);

    $result = Artisan::call('config:clear');
    expect($result)->toBe(0);
})->skip(version_compare(Application::VERSION, '11.0.0', '<'), <<<'MESSAGE'
Due to Laravel 11's new project structure, we only run this on Laravel 11+.

The intention of this test is to ensure that we don't put any unseralizable values in the config.

Running against 11+ should still give a good amount of assurance across other framework versions.
MESSAGE);
