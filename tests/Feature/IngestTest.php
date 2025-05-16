<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use RuntimeException;
use Tests\TestCase;

use function expect;

class IngestTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_handles_ingesting_zero_records()
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
            $exceptions[] = $e;
        });
        $ingest = $this->fakeIngest();
        $this->core->sensor->requestSensor = fn () => throw new RuntimeException('Whoops request!');
        $this->core->sensor->exceptionSensor = fn () => throw new RuntimeException('Whoops exception!');
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        expect($exceptions)->toHaveCount(1);
        expect($exceptions[0]->getMessage())->toBe('Whoops exception!');
        expect($ingest->latestWriteAsString())->toBe('[]');
    }
}
