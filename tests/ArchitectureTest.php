<?php

namespace Tests;

use Illuminate\Support\LazyCollection;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

use function dd;
use function explode;
use function in_array;
use function str_replace;

class ArchitectureTest extends TestCase
{
    const SRC_PATH = __DIR__.'/../src/';

    public function test_marked_internal(): void
    {
        dd('here');
        $ignore = [
            \Laravel\Nightwatch\Records\QueuedJob::class,
            \Laravel\Nightwatch\Records\Mail::class,
            \Laravel\Nightwatch\Records\CacheEvent::class,
            \Laravel\Nightwatch\Records\Request::class,
            \Laravel\Nightwatch\Records\Command::class,
            \Laravel\Nightwatch\Records\Query::class,
            \Laravel\Nightwatch\Records\Notification::class,
            \Laravel\Nightwatch\Records\Exception::class,
            \Laravel\Nightwatch\Records\Exception::class,
            \Laravel\Nightwatch\Records\OutgoingRequest::class,
            \Laravel\Nightwatch\Http\Middleware\Sample::class,
            \Laravel\Nightwatch\Facades\Nightwatch::class,
        ];

        foreach ($this->classes() as $class) {
            if (in_array($class->getName(), $ignore, true)) {
                continue;
            }

            $this->assertContains(' * @internal', explode("\n", $class->getDocComment()), "[{$class->getName()}] is not marked as internal. Add the @internal docblock tag to it or ignore it");
        }
    }

    private function classes(): iterable
    {
        return new LazyCollection(function () {
            $files = Finder::create()->files()->in(self::SRC_PATH);

            foreach ($files as $file) {
                yield new ReflectionClass(
                    'Laravel\\Nightwatch\\'.str_replace([self::SRC_PATH, '.php', '/'], ['', '', '\\'], $file->getPathname())
                );
            }
        });
    }
}
