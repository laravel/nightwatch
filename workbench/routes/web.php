<?php

use App\Jobs\MyJob;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $number = rand(0, 10);

    $url = request()->getScheme().'://tim:abc123@'.request()->getHost().':'.request()->getPort()."/kitchen-sink/{$number}?number={$number}";

    return <<<HTML
        <a target="_blank" href="{$url}">
            Kitchen Sink
        </a>
    HTML;
});

Route::get('/kitchen-sink/{number}', function () {
    DB::table('users')->get();

    return new class implements Responsable {
        public function toResponse($request)
        {
            DB::table('users')->get();

            return response(str_repeat('abc.', rand(0, 10_000)));
        }
    };
});
