<?php

use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\SensorManager;

use function Pest\Laravel\get;

it('goes fast', function () {
    Config::set('logging.default', 'null');
    Http::preventStrayRequests();
    $response = Http::response('ok');
    Http::fake(fn () => $response);

    Benchmark::dd([
        function () {
            Http::get('https://laravel.com');
            Http::get('https://laravel.com');
            Http::get('https://laravel.com');
        },
    ], 300);
})->skip();
