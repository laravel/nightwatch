<?php

use App\Http\UserController;
use App\Jobs\MyJob;
use App\Notifications\MyNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    DB::table('users')->get('name');

    MyJob::dispatch();

    Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);

    report('Hello world!');

    return 'ok';
});
