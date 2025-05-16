<?php

use Illuminate\Cache\Events\RetrievingKey;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Hooks\CacheEventListener;

it('gracefully handles exceptions', function () {
    $thrownInCacheEventSensor = false;
    nightwatch()->sensor->cacheEventSensor = function () use (&$thrownInCacheEventSensor) {
        $thrownInCacheEventSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $event = new RetrievingKey(storeName: 'default', key: 'popular_destinations');

    $listener = new CacheEventListener(nightwatch());
    $listener($event);

    expect($thrownInCacheEventSensor)->toBeTrue();
    expect(nightwatch()->executionState->exceptions)->toBe(1);
})->skip(fn () => ! Compatibility::$cacheFailuresCapturable, 'Requires a more recent framework version');
