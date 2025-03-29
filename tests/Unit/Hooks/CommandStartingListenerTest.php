<?php

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Console\Kernel;
use Laravel\Nightwatch\Hooks\CommandStartingListener;

it('gracefully handles exceptions', function () {
    $events = app(Dispatcher::class);
    $kernel = app(Kernel::class);
    $event = new class extends CommandStarting
    {
        public function __construct()
        {
            //
        }
    };

    $listener = new CommandStartingListener($events, nightwatch(), $kernel);
    $listener($event);

    expect(nightwatch()->state->exceptions)->toBe(2);
});
