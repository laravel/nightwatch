<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Tests\TestCase;

use function hash;
use function now;

class CacheEventSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_can_ingest_cache_misses()
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function () {
            Cache::get('users:345');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 1);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$cacheStoreNameCapturable ? hash('xxh128', 'array,users:345') : hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
                'key' => 'users:345',
                'type' => 'miss',
                'duration' => 0,
                'ttl' => 0,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_hits()
    {
        $ingest = $this->fakeIngest();
        Cache::put('users:345', 'xxxx');
        Route::post('/users', function () {
            Cache::get('users:345');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 2);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$cacheStoreNameCapturable ? hash('xxh128', 'array,users:345') : hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'before_middleware',
                'user' => '',
                'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => 0,
                'ttl' => 0,
            ],
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$cacheStoreNameCapturable ? hash('xxh128', 'array,users:345') : hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
                'key' => 'users:345',
                'type' => 'hit',
                'duration' => 0,
                'ttl' => 0,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_hits_and_misses_with_multiple_keys()
    {
        $ingest = $this->fakeIngest();
        Config::set('cache.stores.custom', [
            'driver' => 'custom',
            'events' => true,
        ]);
        Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
        {
            public function many($key)
            {
                Date::setTestNow(now()->addMicroseconds(2500));

                return parent::many($key);
            }
        }, [
            'events' => true,
        ]));

        Route::post('/users', function () {
            Cache::driver('custom')->put('users:345', 'xxxx');
            Cache::driver('custom')->getMultiple(['users:345', 'users:678']);
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 3);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => 0,
                'ttl' => 0,
            ],
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => Compatibility::$cacheDurationCapturable ? 946688523.456789 : 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:345',
                'type' => 'hit',
                'duration' => Compatibility::$cacheDurationCapturable ? 2500 : 0,
                'ttl' => 0,
            ],
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => Compatibility::$cacheDurationCapturable ? 946688523.456789 : 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:678'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:678',
                'type' => 'miss',
                'duration' => Compatibility::$cacheDurationCapturable ? 2500 : 0,
                'ttl' => 0,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_writes()
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function () {
            Cache::put('users:345', 'xxxx', 60);
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertLatestWrite('request:0.cache_events', 1);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => Compatibility::$cacheDurationCapturable ? 946688523.456789 : 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$cacheStoreNameCapturable ? hash('xxh128', 'array,users:345') : hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => 0,
                'ttl' => 60,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_write_failures()
    {
        $this->markTestSkippedWhen(! Compatibility::$cacheFailuresCapturable, 'Requires a more recent framework version');

        $ingest = $this->fakeIngest();
        Config::set('cache.stores.custom', [
            'driver' => 'custom',
            'events' => true,
        ]);
        Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds)
            {
                return false;
            }
        }, [
            'events' => true,
        ]));
        Route::post('/users', function () {
            Cache::driver('custom')->put('users:345', 'xxxx', 60);
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 1);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:345',
                'type' => 'write-failure',
                'duration' => 0,
                'ttl' => 60,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_writes_with_multiple_keys()
    {
        $ingest = $this->fakeIngest();
        Config::set('cache.stores.custom', [
            'driver' => 'custom',
            'events' => true,
        ]);
        Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
        {
            public function putMany(array $values, $seconds)
            {
                Date::setTestNow(now()->addMicroseconds(2500));

                return parent::putMany($values, $seconds);
            }
        }, [
            'events' => true,
        ]));

        Route::post('/users', function () {
            Cache::driver('custom')->putMany(['users:345' => 'abc', 'users:678' => 'def'], 60);
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 2);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => Compatibility::$cacheDurationCapturable ? 946688523.456789 : 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => Compatibility::$cacheDurationCapturable ? 2500 : 0,
                'ttl' => 60,
            ],
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => Compatibility::$cacheDurationCapturable ? 946688523.456789 : 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:678'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:678',
                'type' => 'write',
                'duration' => Compatibility::$cacheDurationCapturable ? 2500 : 0,
                'ttl' => 60,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_deletes()
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function () {
            Cache::put('users:345', 'xxxx');
            Cache::forget('users:345');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 2);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$cacheStoreNameCapturable ? hash('xxh128', 'array,users:345') : hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
                'key' => 'users:345',
                'type' => 'write',
                'duration' => 0,
                'ttl' => 0,
            ],
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$cacheStoreNameCapturable ? hash('xxh128', 'array,users:345') : hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',

                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
                'key' => 'users:345',
                'type' => 'delete',
                'duration' => 0,
                'ttl' => 0,
            ],
        ]);
    }

    public function test_it_can_ingest_cache_delete_failures()
    {
        $this->markTestSkippedWhen(! Compatibility::$cacheFailuresCapturable, 'Requires a more recent framework version');

        $ingest = $this->fakeIngest();
        Config::set('cache.stores.custom', [
            'driver' => 'custom',
            'events' => true,
        ]);
        Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
        {
            public function forget($key)
            {
                return false;
            }
        }, [
            'events' => true,
        ]));
        Route::post('/users', function () {
            Cache::driver('custom')->forget('users:345');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.cache_events', 1);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:345',
                'type' => 'delete-failure',
                'duration' => 0,
                'ttl' => 0,
            ],
        ]);
    }

    public function test_it_handles_cache_drivers_with_no_store_configured()
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function () {
            Cache::repository(new ArrayStore)->get('users:345');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('cache-event:0.store', '');
    }

    public function test_it_captures_duration_in_microseconds()
    {
        $this->markTestSkippedWhen(! Compatibility::$cacheDurationCapturable, 'Requires a more recent framework version');

        $ingest = $this->fakeIngest();
        Config::set('cache.stores.custom', [
            'driver' => 'custom',
            'events' => true,
        ]);
        Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
        {
            public function get($key)
            {
                Date::setTestNow(now()->addMicroseconds(2500));

                return parent::get($key);
            }
        }, [
            'events' => true,
        ]));
        Route::post('/users', function () {
            Cache::driver('custom')->get('users:345');
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('cache-event:*', [
            [
                'v' => 1,
                't' => 'cache-event',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', ',users:345'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'store' => '',
                'key' => 'users:345',
                'type' => 'miss',
                'duration' => 2500,
                'ttl' => 0,
            ],
        ]);
    }
}
