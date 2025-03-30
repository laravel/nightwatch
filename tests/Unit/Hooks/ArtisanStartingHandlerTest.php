<?php

use Illuminate\Console\Events\ArtisanStarting;
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
});
