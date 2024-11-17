<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Hooks\QueryExecutedListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function query(QueryExecuted $event, array $trace): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $listener = new QueryExecutedListener($sensor);
    $event = new QueryExecuted('select * from "users"', [], 5, DB::connection());

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
