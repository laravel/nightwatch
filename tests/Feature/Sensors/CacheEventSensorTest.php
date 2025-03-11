<?php

use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeAll(function () {
    forceRequestExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
});

it('can ingest cache misses', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Cache::driver('array')->get('users:345');
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
            'key' => 'users:345',
            'type' => 'miss',
            'duration' => 0,
            'ttl' => 0,
        ],
    ]);
});

it('can ingest cache hits', function () {
    $ingest = fakeIngest();
    Cache::driver('array')->put('users:345', 'xxxx');
    Route::post('/users', function () {
        Cache::driver('array')->get('users:345');
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
            'key' => 'users:345',
            'type' => 'hit',
            'duration' => 0,
            'ttl' => 0,
        ],
    ]);
});

it('can ingest cache hits and misses with multiple keys', function () {
    $ingest = fakeIngest();
    Config::set('cache.stores.custom', [
        'driver' => 'custom',
        'events' => true,
    ]);
    Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
    {
        public function many($key)
        {
            travelTo(now()->addMicroseconds(2500));

            return parent::many($key);
        }
    }, [
        'events' => true,
    ]));

    Route::post('/users', function () {
        Cache::driver('custom')->put('users:345', 'xxxx');
        Cache::driver('custom')->getMultiple(['users:345', 'users:678']);
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => '',
            'key' => 'users:678',
            'type' => 'miss',
            'duration' => Compatibility::$cacheDurationCapturable ? 2500 : 0,
            'ttl' => 0,
        ],
    ]);
});

it('can ingest cache writes', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Cache::driver('array')->put('users:345', 'xxxx', 60);
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
            'key' => 'users:345',
            'type' => 'write',
            'duration' => 0,
            'ttl' => 60,
        ],
    ]);
});

it('can ingest cache write failures', function () {
    $ingest = fakeIngest();
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

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => '',
            'key' => 'users:345',
            'type' => 'write-failure',
            'duration' => 0,
            'ttl' => 60,
        ],
    ]);
})->skip(fn () => ! Compatibility::$cacheFailuresCapturable, 'Requires a more recent framework version');

it('can ingest cache writes with multiple keys', function () {
    $ingest = fakeIngest();
    Config::set('cache.stores.custom', [
        'driver' => 'custom',
        'events' => true,
    ]);
    Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
    {
        public function putMany(array $values, $seconds)
        {
            travelTo(now()->addMicroseconds(2500));

            return parent::putMany($values, $seconds);
        }
    }, [
        'events' => true,
    ]));

    Route::post('/users', function () {
        Cache::driver('custom')->putMany(['users:345' => 'abc', 'users:678' => 'def'], 60);
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => '',
            'key' => 'users:678',
            'type' => 'write',
            'duration' => Compatibility::$cacheDurationCapturable ? 2500 : 0,
            'ttl' => 60,
        ],
    ]);
});

it('can ingest cache deletes', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Cache::driver('array')->put('users:345', 'xxxx');
        Cache::driver('array')->forget('users:345');
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => Compatibility::$cacheStoreNameCapturable ? 'array' : '',
            'key' => 'users:345',
            'type' => 'delete',
            'duration' => 0,
            'ttl' => 0,
        ],
    ]);
});

it('can ingest cache delete failures', function () {
    $ingest = fakeIngest();
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

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => '',
            'key' => 'users:345',
            'type' => 'delete-failure',
            'duration' => 0,
            'ttl' => 0,
        ],
    ]);
})->skip(fn () => ! Compatibility::$cacheFailuresCapturable, 'Requires a more recent framework version');

it('handles cache drivers with no store configured', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Cache::repository(new ArrayStore)->get('users:345');
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('cache-event:0.store', '');
});

it('captures duration in microseconds', function () {
    $ingest = fakeIngest();
    Config::set('cache.stores.custom', [
        'driver' => 'custom',
        'events' => true,
    ]);
    Cache::extend('custom', fn () => Cache::repository(new class extends ArrayStore
    {
        public function get($key)
        {
            travelTo(now()->addMicroseconds(2500));

            return parent::get($key);
        }
    }, [
        'events' => true,
    ]));
    Route::post('/users', function () {
        Cache::driver('custom')->get('users:345');
    });

    $response = post('/users');

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
            'execution_stage' => 'action',
            'user' => '',
            'store' => '',
            'key' => 'users:345',
            'type' => 'miss',
            'duration' => 2500,
            'ttl' => 0,
        ],
    ]);
})->skip(fn () => ! Compatibility::$cacheDurationCapturable, 'Requires a more recent framework version');
