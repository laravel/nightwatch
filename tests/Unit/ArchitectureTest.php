<?php

namespace Tests\Unit;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

use function explode;
use function in_array;
use function str_replace;

class ArchitectureTest extends TestCase
{
    const SRC_PATH = __DIR__.'/../../src/';

    public function test_classes_are_internal(): void
    {
        $except = [
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
            if (in_array($class->getName(), $except, true)) {
                continue;
            }

            $this->assertContains(' * @internal', explode("\n", $class->getDocComment()), "[{$class->getName()}] is not marked as internal. Add the @internal docblock tag to it or ignore it");
        }
    }

    public function test_classes_are_final(): void
    {
        foreach ($this->classes() as $class) {
            if ($class->isInterface() || $class->isTrait()) {
                continue;
            }

            $this->assertTrue($class->isFinal(), "[{$class->getName()} is not final");
        }
    }

    private function classes(): iterable
    {
        $files = Finder::create()->files()->in(self::SRC_PATH);

        foreach ($files as $file) {
            yield new ReflectionClass(
                'Laravel\\Nightwatch\\'.str_replace([self::SRC_PATH, '.php', '/'], ['', '', '\\'], $file->getPathname())
            );
        }
    }
}
