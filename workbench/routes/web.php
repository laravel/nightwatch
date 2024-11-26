<?php

use App\Http\UserController;
use App\Jobs\MyJob;
use App\Mail\MyMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    DB::table('users')->get('name');

    MyJob::dispatch();

    Mail::to('tim@laravel.com')->send(new MyMail);

    Http::get('https://laravel.com');

    report('Hello world!');

    return 'ok';
});
