<?php

use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Foundation\Application;
use Laravel\Nightwatch\Hooks\ArtisanStartingListener;

it('gracefully handles exceptions', function () {
    $event = new class extends ArtisanStarting
    {
        public function __construct()
        {
            //
        }
    };

    $listener = new ArtisanStartingListener(nightwatch());
    $listener($event);

    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
})->skip(version_compare(Application::VERSION, '12.0.0', '<'), <<<'MESSAGE'
This test only fails when there are type declations which where introduced in 12.x
MESSAGE);
