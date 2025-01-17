<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Hooks\QueryExecutedListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function query(QueryExecuted $event, array $trace): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $listener = new QueryExecutedListener($nightwatch);
    $event = new QueryExecuted('select * from "users"', [], 5, DB::connection());

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
