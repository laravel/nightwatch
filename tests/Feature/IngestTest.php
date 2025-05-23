<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use RuntimeException;
use Tests\TestCase;

class IngestTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_handles_ingesting_zero_records(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $ingest = $this->fakeIngest();
        $this->core->sensor->requestSensor = fn () => throw new RuntimeException('Whoops request!');
        $this->core->sensor->exceptionSensor = fn () => throw new RuntimeException('Whoops exception!');
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops exception!', $exceptions[0]->getMessage());
        $this->assertSame('[]', $ingest->latestWriteAsString());
    }
}
