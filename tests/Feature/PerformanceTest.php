<?php

use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;

it('goes fast', function () {
    Config::set('logging.default', 'null');

    Benchmark::dd([
        function () {
            Artisan::call('nightwatch:hammer');
        },
    ], 50);
});
