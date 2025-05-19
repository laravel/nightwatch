<?php

namespace Tests\Unit\Hooks;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RequestLifecycleIsLongerThanHandler;
use RuntimeException;
use Tests\TestCase;

use function now;

class RequestLifecycleIsLongerThanHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_gracefully_handles_exceptions_while_capturing_stage(): void
    {
        $ingest = $this->fakeIngest();
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $startedAt = now();
        $request = Request::create('/test');
        $response = new Response;

        $handler = new RequestLifecycleIsLongerThanHandler($this->core);
        $handler($startedAt, $request, $response);

        $this->assertTrue($thrownInStageSensor);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);
            $this->assertSame('exception', $records[0]['t']);
            $this->assertSame('request', $records[1]['t']);

            return true;
        });
    }

    public function test_it_gracefully_handles_exceptions_while_capturing_user(): void
    {
        $ingest = $this->fakeIngest();
        $thrownInUserSensor = false;
        $this->core->sensor->userSensor = function () use (&$thrownInUserSensor): void {
            $thrownInUserSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $startedAt = now();
        $request = Request::create('/test');
        $response = new Response;

        $handler = new RequestLifecycleIsLongerThanHandler($this->core);
        $handler($startedAt, $request, $response);

        $this->assertTrue($thrownInUserSensor);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);
            $this->assertSame('exception', $records[0]['t']);
            $this->assertSame('request', $records[1]['t']);

            return true;
        });
    }

    public function test_it_gracefully_handles_exceptions_while_capturing_request(): void
    {
        $ingest = $this->fakeIngest();
        $thrownInRequestSensor = false;
        $this->core->sensor->requestSensor = function () use (&$thrownInRequestSensor): void {
            $thrownInRequestSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $startedAt = now();
        $request = Request::create('/test');
        $response = new Response;

        $handler = new RequestLifecycleIsLongerThanHandler($this->core);
        $handler($startedAt, $request, $response);

        $this->assertTrue($thrownInRequestSensor);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);
            $this->assertSame('exception', $records[0]['t']);

            return true;
        });
    }
}
