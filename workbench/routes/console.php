<?php

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Notifications\MyNotification;
use GuzzleHttp\Psr7\Request;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

use function React\Async\await;

Artisan::command('kitchen-sink', function () {
    DB::table('users')->get('name');

    MyJob::dispatch();

    Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);

    Mail::to('tim@laravel.com')->send(new MyMail);

    Http::get('https://laravel.com');

    report('Hello world!');

    Cache::get('user:55');
    Cache::put('user:55', 'Taylor', 60);
    Cache::putMany(['user:56' => 'Jess', 'user:57' => 'Tim'], 60);
    Cache::getMultiple(['user:56', 'user:57']);
    Cache::forget('user:55');

    $this->info('done');
});
