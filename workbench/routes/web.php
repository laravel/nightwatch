<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    DB::table('users')->get('name');

    report('Whoops!');

    return 'ok';
});
