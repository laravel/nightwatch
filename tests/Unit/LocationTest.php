<?php

namespace Tests\Unit;

use Exception;
use ReflectionClass;
use Tests\TestCase;

use function base_path;
use function public_path;

class LocationTest extends TestCase
{
    public function test_it_can_find_the_file_in_the_trace(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_skips_vendor_files_in_trace_when_a_non_vendor_file_exists(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_skips_artisan_files_when_a_non_vendor_file_exists(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_skips_index_php_file_when_a_non_vendor_file_exists(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_handles_missing_line_number(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_uses_the_path_of_the_exception_when_it_is_non_vendor(): void
    {
        $location = $this->core->sensor->location;
        $e = new Exception;
        $reflectedException = new ReflectionClass($e);
        $reflectedException->getProperty('file')->setValue($e, base_path('app/Models/User.php'));
        $reflectedException->getProperty('line')->setValue($e, 5);

        $file = $location->forException($e);

        $this->assertSame(['app/Models/User.php', 5], $file);
    }

    public function test_it_falls_back_to_trace_when_exception_is_thrown_in_vendor_frame(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_uses_the_thrown_location_when_no_non_vendor_file_is_found(): void
    {
        $location = $this->core->sensor->location;
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
    }

    public function test_it_finds_first_non_vendor_frame_from_query_trace(): void
    {
        $location = $this->core->sensor->location;

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
    }

    public function test_it_ignores_internal_frames_when_there_is_no_non_vendor_frames(): void
    {
        $location = $this->core->sensor->location;

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
    }

    public function test_it_uses_first_non_internal_vendor_frames(): void
    {
        $location = $this->core->sensor->location;

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
    }
}
