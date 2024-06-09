<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Cache miss:
    Cache::get('users:345');

    // Cache hit:
    Cache::put('users:123', 'xxxx');
    Cache::get('users:123');

    // Query:
    DB::table('users')->get();

    return view('welcome');
});
