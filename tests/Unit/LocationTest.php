<?php

it('can find the file in the trace', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('skips vendor files in trace when a non-vendor file exists', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('skips artisan files when a non-vendor file exists', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('skips index.php file when a non-vendor file exists', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('handles missing line number', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', null], $file);
});

it('uses the path of the exception when it is non vendor', function () {
    $location = nightwatch()->sensor->location;
    $e = new Exception;
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('app/Models/User.php'));
    $reflectedException->getProperty('line')->setValue($e, 5);

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('falls back to trace when exception is thrown in vendor frame', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('uses the thrown location when no non-vendor file is found', function () {
    $location = nightwatch()->sensor->location;
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

    $file = $location->forException($e);

    $this->assertSame(['vendor/foo/bar/Baz1.php', 5], $file);
});

it('finds first non-vendor frame from query trace', function () {
    $location = nightwatch()->sensor->location;

    $file = $location->forQueryTrace([
        [
            'file' => base_path('vendor/laravel/nightwatch/src/NightwatchServiceProvider.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('vendor/laravel/framework/src/Illuminate/Database/Connection.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('vendor/foo/bar/Baz.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('app/Models/User.php'),
            'line' => 5,
        ],
        [
            'file' => base_path('app/Http/Controllers/UserController.php'),
            'line' => 55,
        ],
    ]);

    $this->assertSame(['app/Models/User.php', 5], $file);
});

it('ignores internal frames when there is no non-vendor frames', function () {
    $location = nightwatch()->sensor->location;

    $file = $location->forQueryTrace([
        [
            'file' => base_path('vendor/laravel/nightwatch/src/NightwatchServiceProvider.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('vendor/laravel/framework/src/Illuminate/Database/Connection.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('vendor/foo/bar/Baz.php'),
            'line' => 9,
        ],
    ]);

    $this->assertSame(['vendor/foo/bar/Baz.php', 9], $file);
});

it('uses first non-internal vendor frames', function () {
    $location = nightwatch()->sensor->location;

    $file = $location->forQueryTrace([
        [
            'file' => base_path('vendor/laravel/nightwatch/src/NightwatchServiceProvider.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('vendor/foo/bar/Baz1.php'),
            'line' => 9,
        ],
        [
            'file' => base_path('vendor/foo/bar/Baz2.php'),
            'line' => 5,
        ],
    ]);

    $this->assertSame(['vendor/foo/bar/Baz1.php', 9], $file);
});
