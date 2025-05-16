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

    expect($thrownInStageSensor)->toBeTrue();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        expect($records)->toHaveCount(2);
        expect($records[0]['t'])->toBe('exception');
        expect($records[1]['t'])->toBe('request');

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

    expect($thrownInUserSensor)->toBeTrue();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        expect($records)->toHaveCount(2);
        expect($records[0]['t'])->toBe('exception');
        expect($records[1]['t'])->toBe('request');

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

    expect($thrownInRequestSensor)->toBeTrue();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite(function ($records) {
        expect($records)->toHaveCount(1);
        expect($records[0]['t'])->toBe('exception');

        return true;
    });
});
