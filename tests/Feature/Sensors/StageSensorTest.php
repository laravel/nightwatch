<?php

namespace Tests\Feature\Sensors;

use Illuminate\Foundation\Events\Terminating;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Tests\TestCase;

use function class_exists;
use function now;

class StageSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_captures_the_terminating_phase_when_the_terminating_event_does_not_exist()
    {
        $this->freezeTime();
        Compatibility::$terminatingEventExists = false;
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            $this->app->terminating(function () {
                $this->travelTo(now()->addMicroseconds(123));
            });

            return [];
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.terminating', 123);
    }

    public function test_it_captures_the_terminating_phase_when_the_terminating_event_does_exist()
    {
        $this->markTestSkippedWhen(! class_exists(Terminating::class), 'Terminating event does not exist');

        $this->freezeTime();
        Compatibility::$terminatingEventExists = true;
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            $this->app->terminating(function () {
                $this->travelTo(now()->addMicroseconds(123));
            });

            return [];
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.terminating', 123);
    }
}
