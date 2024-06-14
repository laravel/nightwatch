<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\Sensors\OutgoingRequestSensor;

Route::get('/', function () {
    // Cache events. miss:
    Cache::get('users:345');

    // Cache events. hit:
    Cache::put('users:123', 'xxxx');
    Cache::get('users:123');

    // Queries:
    DB::table('users')->get();

    // Outgoing requests:
    Http::get('https://laravel.com');

    // Exceptions:
    report('Whoops!');

    // Requests:
    return view('welcome');
});

Route::get('hammer', function () {
    Artisan::call('nightwatch:hammer');
});

