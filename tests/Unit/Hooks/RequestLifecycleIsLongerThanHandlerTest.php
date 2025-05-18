<?php

use Illuminate\Http\Response;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RequestLifecycleIsLongerThanHandler;

beforeAll(function () {
    forceRequestExecutionState();
});

it('gracefully handles exceptions while capturing stage', function () {
    $ingest = fakeIngest();
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->executionState->stage = ExecutionStage::Bootstrap;

    $startedAt = now();
    $request = Request::create('/test');
    $response = new Response;

    $handler = new RequestLifecycleIsLongerThanHandler(nightwatch());
    $handler($startedAt, $request, $response);

    $this->assertTrue($thrownInStageSensor);
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        $this->assertCount(2, $records);
        $this->assertSame('exception', $records[0]['t']);
        $this->assertSame('request', $records[1]['t']);

        return true;
    });
});

it('gracefully handles exceptions while capturing user', function () {
    $ingest = fakeIngest();
    $thrownInUserSensor = false;
    nightwatch()->sensor->userSensor = function () use (&$thrownInUserSensor) {
        $thrownInUserSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $startedAt = now();
    $request = Request::create('/test');
    $response = new Response;

    $handler = new RequestLifecycleIsLongerThanHandler(nightwatch());
    $handler($startedAt, $request, $response);

    $this->assertTrue($thrownInUserSensor);
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        $this->assertCount(2, $records);
        $this->assertSame('exception', $records[0]['t']);
        $this->assertSame('request', $records[1]['t']);

        return true;
    });
});

it('gracefully handles exceptions while capturing request', function () {
    $ingest = fakeIngest();
    $thrownInRequestSensor = false;
    nightwatch()->sensor->requestSensor = function () use (&$thrownInRequestSensor) {
        $thrownInRequestSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $startedAt = now();
    $request = Request::create('/test');
    $response = new Response;

    $handler = new RequestLifecycleIsLongerThanHandler(nightwatch());
    $handler($startedAt, $request, $response);

    $this->assertTrue($thrownInRequestSensor);
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        $this->assertCount(1, $records);
        $this->assertSame('exception', $records[0]['t']);

        return true;
    });
});
