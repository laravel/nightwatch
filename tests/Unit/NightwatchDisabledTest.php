<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Foundation\Env;
use RuntimeException;
use Tests\TestCase;

class NightwatchDisabledTest extends TestCase
{
    protected function setUp(): void
    {
        Env::getRepository()->set('NIGHTWATCH_ENABLED', '0');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Env::getRepository()->clear('NIGHTWATCH_ENABLED');
    }

    public function test_it_can_disable_nightwatch_via_the_environment(): void
    {
        $this->assertFalse($this->core->enabled());
    }

    public function test_it_gracefully_ignores_reported_exceptions_when_nightwatch_is_disabled(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => Nightwatch::report(new RuntimeException));

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(0);
        $this->assertSame(0, $this->core->executionState->exceptions);
    }

    public function test_it_gracefully_ignores_logs_when_nightwatch_is_disabled(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => Log::channel('nightwatch')->info('Hello world'));

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(0);
        $this->assertSame(0, $this->core->executionState->logs);
        $this->assertSame(0, $this->core->executionState->exceptions);
    }
}
