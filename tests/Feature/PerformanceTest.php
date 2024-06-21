<?php

use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('goes fast', function () {
    Config::set('logging.default', 'null');
    Http::preventStrayRequests();
    $response = Http::response('ok');
    Http::fake(fn () => $response);

    $length = null;
    Benchmark::dd([
        function () {
            return base_path('foo/bar/baz');
        },
        function () use (&$length) {
            $length ??= base_path('foo/bar/baz');
        },
    ], 300);
})->skip();
