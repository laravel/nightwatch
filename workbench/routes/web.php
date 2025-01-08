<?php

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Notifications\MyNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    // This should not be captured.
    Artisan::call('inspire');

    DB::table('users')->get('name');

    MyJob::dispatch();

    Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);

    Mail::to('tim@laravel.com')->send(new MyMail);

    Http::get('https://laravel.com');

    Context::add('some', 'extra');
    Log::channel('nightwatch')->debug('Hello world!', [
        'some' => 'context',
    ]);

    report('Hello world!');

    Cache::get('user:55');
    Cache::put('user:55', 'Taylor', 60);
    Cache::putMany(['user:56' => 'Jess', 'user:57' => 'Tim'], 60);
    Cache::getMultiple(['user:56', 'user:57']);
    Cache::forget('user:55');

    return 'ok';
});
