<?php

use Illuminate\Support\Facades\Cache;
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

    Cache::get('user:55');
    Cache::put('user:55', 'Taylor', 60);
    Cache::putMany(['user:56' => 'Jess', 'user:57' => 'Tim'], 60);
    Cache::getMultiple(['user:56', 'user:57']);
    Cache::forget('user:55');

    return 'ok';
});
