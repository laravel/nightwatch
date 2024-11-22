<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    DB::table('users')->get('name');

    report('Hello world!');

    Cache::get('user:55');
    Cache::put('user:55', 'Taylor', 60);
    Cache::get('user:55');
    Cache::forget('user:55');

    return 'ok';
});
