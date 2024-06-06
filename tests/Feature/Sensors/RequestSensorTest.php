<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Sensors\RequestSensor;

use function Pest\Laravel\call;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

it('returns a request record', function () {
    /** @var RequestSensor */
    $sensor = app(RequestSensor::class);

    travelTo(Carbon::parse('2000-01-01 01:02:03'));
    Route::post('/users/{user}', function () {
        travelTo(now()->addSecond()->addMilliseconds(234));

        return 'OK';
    });

    $response = postJson('/users/{user}', [
        'foo' => 'bar'
    ]);

    $response->assertOk();
    expect($sensor->records)->toHaveCount(1);
    expect($sensor->records[0])->toBe([
        'timestamp' => '2000-01-01 01:02:03',
        // 'deploy_id' => '',
        // 'server' => '',
        'group' => '',
        // 'trace_id' => '',
        'method' => 'GET',
        'route' => '/users/{user}',
        'path' => '/users/345',
        'user' => '',
        'ip' => '127.0.0.1',
        'duration' => 1234,
        'status_code' => '200',
        'request_size_kilobytes' => 13,
        'response_size_kilobytes' => 2,
    ]);
});

it('handles requests with no content-length header, such as chunked requests')->todo();

it('handles routes with domains')->todo(); // 'path' field or dedicated column

it('handles unknown routes')->todo(); // 'route' field

it('records authenticated user')->todo(); // 'user' field

it('handles streams')->todo(); // Content-Length

it('handles 304 Not Modified responses')->todo(); // Content-Length

it('handles HEAD requests')->todo(); // Content-Length

it('handles responses using Transfer-Encoding headers')->todo(); // Content-Length
