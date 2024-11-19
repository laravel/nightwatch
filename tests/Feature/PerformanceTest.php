<?php

use Illuminate\Support\Benchmark;

it('goes fast', function () {
    $config = app('config');

    Benchmark::dd([
        function () {
            // ...
        },
        function () {
            // ...
        },
        function () {
            // ...
        },
    ], 1000);
})->skip();
