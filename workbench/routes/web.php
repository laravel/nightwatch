<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    Cache::get('users:345');

    DB::table('users')->get();

    return view('welcome');
});
