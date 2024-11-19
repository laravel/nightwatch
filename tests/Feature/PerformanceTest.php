<?php

use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('goes fast', function () {
    $config = app('config');

    Benchmark::dd([
        function () {
            //
        },
        function () {
            //
        },
        function () {
            //
        },
    ], 1000);
})->skip();
