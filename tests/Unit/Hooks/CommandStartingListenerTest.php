<?php

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bus\PendingDispatch;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\CommandStartingListener;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

it('gracefully handles exceptions', function () {
    $unrecoverableExceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
        $unrecoverableExceptions[] = $e;
    });
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

    expect($unrecoverableExceptions)->toHaveCount(1);
})->skip(version_compare(Application::VERSION, '12.0.0', '<'), <<<'MESSAGE'
This test only fails when there are type declations which where introduced in 12.x
MESSAGE);

it('gracefully handles custom kernel implementations', function () {
    $events = app(Dispatcher::class);
    $kernel = new class implements Kernel
    {
        public function bootstrap()
        {
            //
        }

        public function handle($input, $output = null)
        {
            return 0;
        }

        public function call($command, array $parameters = [], $outputBuffer = null)
        {
            return 0;
        }

        public function queue($command, array $parameters = [])
        {
            return new PendingDispatch(literal());
        }

        public function all()
        {
            return [];
        }

        public function output()
        {
            return '';
        }

        public function terminate($input, $status)
        {
            //
        }
    };
    $event = new CommandStarting('app:command', new StringInput('app:command'), new NullOutput);

    $listener = new CommandStartingListener($events, nightwatch(), $kernel);
    $listener($event);

    expect(nightwatch()->state->exceptions)->toBe(0);
});
