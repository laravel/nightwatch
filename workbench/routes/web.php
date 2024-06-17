<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

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

    return 'ok';
});
