<?php

use App\Http\UserController;
use App\Jobs\MyJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    DB::table('users')->get('name');

    MyJob::dispatch();

    Http::get('https://laravel.com');

    report('Hello world!');

    return 'ok';
});
