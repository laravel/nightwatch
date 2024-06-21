<?php

use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;
use Laravel\Nightwatch\Location;

use function Pest\Laravel\get;

it('can find the file in the trace', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('app/Models/User.php'),
            'line' => 5,
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', 5]);
});

it('skips vendor files in trace when a non-vendor file exists', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('vendor/foo/bar/Baz.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('app/Models/User.php'),
            'line' => 5,
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', 5]);
});

it('skips artisan files when a non-vendor file exists', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('artisan'),
            'line' => 9,
        ],
        [
            'file' => base_path('app/Models/User.php'),
            'line' => 5,
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', 5]);
});

it('skips index.php file when a non-vendor file exists', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => public_path('index.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('app/Models/User.php'),
            'line' => 5,
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', 5]);
});

it('handles missing line number', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('vendor/foo/bar/Baz.php'),
        ],
        [
            'file' => base_path('app/Models/User.php'),
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', null]);
});

it('uses the path of the exception when it is non vendor', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('app/Models/User.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', 5]);
});

it('falls back to trace when exception is thrown in vendor frame', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('vendor/foo/bar/Baz.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('app/Models/User.php'),
            'line' => 5,
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['app/Models/User.php', 5]);
});

it('uses the thrown location when no non-vendor file is found', function () {
    $location = app(Location::class);
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz1.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('vendor/foo/bar/Baz2.php'),
            'line' => 9,
        ],
    ]);

    $file = $location->find($e);

    expect($file)->toBe(['vendor/foo/bar/Baz1.php', 5]);
});

it('handles view exceptions', function () {
    App::setBasePath(realpath(__DIR__.'/../../../nightwatch/workbench'));
    $ingest = fakeIngest();
    Route::view('exception', 'exception');

    $response = get('exception');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exceptions.0.line', 0);
    $ingest->assertLatestWrite('exceptions.0.file', 'resources/views/exception.blade.php');
});
