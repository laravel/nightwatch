<?php

namespace Tests\Feature\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

use function expect;

class NightwatchTest extends TestCase
{
    public function test_it_resolves_to_bound_singleton_instance_of_the_core_class()
    {
        expect(Nightwatch::getFacadeRoot())->toBeInstanceOf(Core::class);

        expect(Nightwatch::getFacadeRoot())->toBe($this->app[Core::class]);

        Facade::clearResolvedInstances();
        expect(Nightwatch::getFacadeRoot())->toBe($this->app[Core::class]);
    }

    public function test_it_silently_discards_unrecoverable_exceptions_by_default()
    {
        (new ReflectionClass(Nightwatch::class))->getProperty('handleUnrecoverableExceptionsUsing')->setValue(null);
        $calls = 0;
        Log::listen(function () use (&$calls) {
            $calls++;
        });

        Nightwatch::unrecoverableExceptionOccurred(new RuntimeException('Whoops!'));

        expect($calls)->toBe(0);
    }

    public function test_it_can_register_a_callback_to_handle_unrecoverable_exceptions()
    {
        $handled = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function (Throwable $e) use (&$handled) {
            $handled[] = $e;
        });

        Nightwatch::unrecoverableExceptionOccurred($first = new RuntimeException('Whoops!'));
        Nightwatch::unrecoverableExceptionOccurred($second = new RuntimeException('Whoops!'));

        expect($handled)->toBe([
            $first,
            $second,
        ]);
    }

    public function test_it_handles_unrecoverable_exceptions_statelessly()
    {
        $this->app->forgetInstance(Core::class);
        $resolved = false;
        Nightwatch::resolved(function () use (&$resolved) {
            $resolved = true;
        });

        $handled = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function (Throwable $e) use (&$handled) {
            $handled[] = $e;
        });
        Nightwatch::unrecoverableExceptionOccurred($first = new RuntimeException('Whoops!'));

        expect($resolved)->toBeFalse();
        expect($handled)->toHaveCount(1);
        expect($this->app->resolved(Core::class))->toBeFalse();
    }

    public function test_it_silences_exceptions_thrown_while_handling_exceptions()
    {
        Nightwatch::handleUnrecoverableExceptionsUsing(function (): object {
            // Should return an object. Returning an int to cause an exception.
            return 5;
        });

        Nightwatch::unrecoverableExceptionOccurred(new RuntimeException('Whoops!'));

        $this->assertTrue(true);
    }
}
