<?php

namespace Tests\Unit\Hooks;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Env;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\CommandStartingListener;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

use function literal;
use function version_compare;

class CommandStartingListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $this->markTestSkippedWhen(version_compare(Application::VERSION, '12.0.0', '<'), <<<'MESSAGE'
            This test only fails when there are type declations which where introduced in 12.x
            MESSAGE);

        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions): void {
            $unrecoverableExceptions[] = $e;
        });
        $events = $this->app[Dispatcher::class];
        $kernel = $this->app[Kernel::class];
        $event = new class extends CommandStarting
        {
            public function __construct()
            {
                //
            }
        };

        $listener = new CommandStartingListener($events, $this->core, $kernel);
        $listener($event);

        $this->assertCount(1, $unrecoverableExceptions);
    }

    public function test_it_gracefully_handles_custom_kernel_implementations(): void
    {
        $events = $this->app[Dispatcher::class];
        $kernel = new class implements Kernel
        {
            public function bootstrap(): void
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

            public function terminate($input, $status): void
            {
                //
            }
        };
        $event = new CommandStarting('app:command', new StringInput('app:command'), new NullOutput);

        $listener = new CommandStartingListener($events, $this->core, $kernel);
        $listener($event);

        $this->assertSame(0, $this->core->executionState->exceptions);
    }

    public function test_it_sets_default_sampling_when_no_environment(): void
    {
        $event = new CommandStarting('app:command', new StringInput(''), new NullOutput);
        $events = $this->app[Dispatcher::class];
        $kernel = $this->app[Kernel::class];

        Nightwatch::sample();

        $listener = new CommandStartingListener($events, $this->core, $kernel);
        $listener($event);

        $this->assertTrue(Nightwatch::sampling());

        // TODO: how to rebind nightwatch and override config to set not sampling to assert against
        Nightwatch::dontSample();

        $listener = new CommandStartingListener($events, $this->core, $kernel);
        $listener($event);

        $this->assertFalse(Nightwatch::sampling());
    }

    public function test_it_sets_sampling_from_environment(): void
    {
        $event = new CommandStarting('app:command', new StringInput(''), new NullOutput);
        $events = $this->app[Dispatcher::class];
        $kernel = $this->app[Kernel::class];

        $listener = new CommandStartingListener($events, $this->core, $kernel);
        $listener($event);

        $this->assertTrue(Nightwatch::sampling());
    }

    public function test_it_sets_not_sampling_from_environment(): void
    {
        $event = new CommandStarting('app:command', new StringInput(''), new NullOutput);
        $events = $this->app[Dispatcher::class];
        $kernel = $this->app[Kernel::class];

        Env::getRepository()->set('NIGHTWATCH_SCHEDULE_TASK_SUBCOMMAND_SAMPLED', '0');

        $listener = new CommandStartingListener($events, $this->core, $kernel);
        $listener($event);

        $this->assertFalse(Nightwatch::sampling());
        Env::getRepository()->clear('NIGHTWATCH_SCHEDULE_TASK_SUBCOMMAND_SAMPLED');
    }
}
