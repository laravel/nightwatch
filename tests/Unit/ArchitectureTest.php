<?php

namespace Tests\Unit;

use FilesystemIterator;
use Laravel\Nightwatch\Core;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

use function count;
use function explode;
use function in_array;
use function str_contains;
use function str_replace;
use function trim;

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
            \Laravel\Nightwatch\Console\Sample::class,
            \Laravel\Nightwatch\Core::class,
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
                $this->assertTrue($this->markedInteral($class), "[{$class->getName()}] is not marked as internal. Add the @internal docblock tag to it or ignore it");
            }
        }
    }

    public function test_core_class_methods_are_correctly_marked_as_api_or_internal(): void
    {
        foreach ((new ReflectionClass(Core::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__construct') {
                continue;
            }

            $this->assertTrue($this->markedInteral($method) || $this->markedApi($method), '['.Core::class."::{$method->getName()}] is not marked as internal or api. Add the @internal docblock to exclude it from the facade or the @api take to include it");
        }
    }

    public function test_core_class_properties_are_correctly_marked_as_api_or_internal(): void
    {
        foreach ((new ReflectionClass(Core::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $this->assertTrue($this->markedInteral($property) || $this->markedApi($property), '['.Core::class."::\${$property->getName()}] is not marked as internal or api. Add the @internal docblock to exclude it from the facade or the @api take to include it");
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

    private function markedInteral(ReflectionClass|ReflectionMethod|ReflectionProperty $item): bool
    {
        $lines = explode("\n", $item->getDocComment());

        if (count($lines) === 1) {
            return $lines[0] === '/** @internal */';
        }

        foreach ($lines as $line) {
            if (str_contains(trim($line), '* @internal')) {
                return true;
            }
        }

        return false;
    }

    private function markedApi(ReflectionClass|ReflectionMethod|ReflectionProperty $item): bool
    {
        $lines = explode("\n", $item->getDocComment());

        if (count($lines) === 1) {
            return $lines[0] === '/** @api */';
        }

        foreach ($lines as $line) {
            if (str_contains(trim($line), '* @api')) {
                return true;
            }
        }

        return false;
    }
}
