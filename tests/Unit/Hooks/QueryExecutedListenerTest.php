<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Hooks\QueryExecutedListener;

it('gracefully handles exceptions', function () {
    $thrownInQuerySensor = false;
    nightwatch()->sensor->querySensor = function () use (&$thrownInQuerySensor) {
        $thrownInQuerySensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new QueryExecuted('select * from "users"', [], 5, DB::connection());

    $listener = new QueryExecutedListener(nightwatch());
    $listener($event);

    expect($thrownInQuerySensor)->toBeTrue();
    expect(nightwatch()->executionState->exceptions)->toBe(1);
});
