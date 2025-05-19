<?php

namespace Tests\Unit\Hooks;

use Illuminate\Cache\Events\RetrievingKey;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Hooks\CacheEventListener;
use RuntimeException;
use Tests\TestCase;

class CacheEventListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $this->markTestSkippedWhen(! Compatibility::$cacheFailuresCapturable, 'Requires a more recent framework version');

        $thrownInCacheEventSensor = false;
        $this->core->sensor->cacheEventSensor = function () use (&$thrownInCacheEventSensor): void {
            $thrownInCacheEventSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $event = new RetrievingKey(storeName: 'default', key: 'popular_destinations');

        $listener = new CacheEventListener($this->core);
        $listener($event);

        $this->assertTrue($thrownInCacheEventSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
