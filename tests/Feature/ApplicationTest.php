<?php

use Illuminate\Support\Facades\Artisan;

it('can cache the config', function () {
    $basePath = app()->basePath();

    $result = Artisan::call('config:cache');
    expect($result)->toBe(0);

    app()->setBasePath($basePath);

    $result = Artisan::call('config:clear');
    expect($result)->toBe(0);
});
