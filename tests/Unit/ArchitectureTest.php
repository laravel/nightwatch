<?php

namespace Tests\Unit;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

use function explode;
use function in_array;
use function str_replace;

class ArchitectureTest extends TestCase
{
    public function test_classes_are_final(): void
    {
        foreach ($this->classes() as $class) {
            if (! $class->isInterface() && ! $class->isTrait()) {
                $this->assertTrue($class->isFinal(), "[{$class->getName()} is not final");
            }
        }
    }

    public function test_classes_are_internal(): void
    {
        $except = [
            \Laravel\Nightwatch\Facades\Nightwatch::class,
            \Laravel\Nightwatch\Http\Middleware\Sample::class,
            \Laravel\Nightwatch\Records\CacheEvent::class,
            \Laravel\Nightwatch\Records\Command::class,
            \Laravel\Nightwatch\Records\Exception::class,
            \Laravel\Nightwatch\Records\Exception::class,
            \Laravel\Nightwatch\Records\Mail::class,
            \Laravel\Nightwatch\Records\Notification::class,
            \Laravel\Nightwatch\Records\OutgoingRequest::class,
            \Laravel\Nightwatch\Records\Query::class,
            \Laravel\Nightwatch\Records\QueuedJob::class,
            \Laravel\Nightwatch\Records\Request::class,
        ];

        foreach ($this->classes() as $class) {
            if (! in_array($class->getName(), $except, true)) {
                $this->assertContains(' * @internal', explode("\n", $class->getDocComment()), "[{$class->getName()}] is not marked as internal. Add the @internal docblock tag to it or ignore it");
            }
        }
    }

    /**
     * @return iterable<ReflectionClass>
     */
    private function classes(): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src = __DIR__.'/../../src/', flags: FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            yield new ReflectionClass(
                'Laravel\\Nightwatch\\'.str_replace([$src, '.php', '/'], ['', '', '\\'], $file->getPathname())
            );
        }
    }
}
