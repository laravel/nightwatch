<?php

use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\RetrievingKey;
use Laravel\Nightwatch\Hooks\CacheEventListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function cacheEvent(CacheEvent $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }

        public function exception(Throwable $e): void
        {
            //
        }
    });

    $listener = new CacheEventListener($nightwatch);
    $event = new RetrievingKey(storeName: 'default', key: 'popular_destinations');

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
