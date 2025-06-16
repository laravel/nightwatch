<?php

namespace Tests;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Contracts\Ingest as IngestContract;
use Laravel\Nightwatch\Ingest;
use Laravel\Nightwatch\Records\Record;
use PHPUnit\Framework\Assert;

use function collect;
use function explode;
use function is_array;
use function json_decode;
use function str_contains;
use function value;

class FakeIngest implements IngestContract
{
    /**
     * @param  Collection<FakeTcpStream>  $streams
     */
    public function __construct(
        private Ingest $ingest,
        private Collection $streams
    ) {
        //
    }

    public function write(Record $record): void
    {
        $this->ingest->write($record);
    }

    public function shouldDigest(bool $bool): void
    {
        $this->ingest->shouldDigest($bool);
    }

    public function digest(): void
    {
        $this->ingest->digest();
    }

    public function ping(): void
    {
        $this->ingest->ping();
    }

    public function flush(): void
    {
        $this->ingest->flush();
    }

    public function assertWrittenTimes(int $expected): self
    {
        Assert::assertSame($expected, $actual = $this->streams->count(), "Expected to have written [{$expected}]. Instead, was written [{$actual}].");

        return $this;
    }

    public function assertWrite(int $index, string|array|Closure $key, mixed $expected = null): self
    {
        Assert::assertGreaterThan($index, $found = $this->streams->count(), 'Expected to have '.($index + 1).' writes. '.$found.' found.');

        $write = json_decode($this->writes()[$index], true, flags: JSON_THROW_ON_ERROR);

        if ($key instanceof Closure) {
            [$key, $expected] = ['*', $key];
        }

        if (is_array($key)) {
            Assert::assertSame($key, $write, 'Failed asserting that the payload matched.');

            return $this;
        }

        if (str_contains($key, ':')) {
            $type = Str::before($key, ':');
            $key = Str::after($key, ':');

            $write = collect($write)->where('t', $type)->values()->all();
        }

        if ($key === '*') {
            if ($expected instanceof Closure) {
                Assert::assertTrue($expected($write), "The expected value was not found at [{$key}].");
            } else {
                Assert::assertSame(value($expected, $write), $write, "The expected value was not found at [{$key}].");
            }
        } else {
            Assert::assertTrue(Arr::has($write, $key), "The key [{$key}] does not exist in the latest write.");
            $actual = Arr::get($write, $key);

            if ($expected instanceof Closure) {
                Assert::assertTrue($expected($actual), "The expected value was not found at [{$key}].");
            } else {
                Assert::assertSame(value($expected, $actual), $actual, "The expected value was not found at [{$key}].");
            }
        }

        return $this;
    }

    public function assertLatestWrite(string|array|Closure $key, mixed $expected = null): self
    {
        return $this->assertWrite($this->streams->count() - 1, $key, $expected);
    }

    public function latestWriteAsString(): ?string
    {
        return $this->streams->last()?->value;
    }

    public function writes(): Collection
    {
        return $this->streams->map(function ($stream) {
            return explode(':', $stream->value, 3)[2];
        });
    }

    public function decodedWrites(): Collection
    {
        return $this->writes()->map(function ($write) {
            return json_decode($write, true, flags: JSON_THROW_ON_ERROR);
        });
    }

    public function forgetWrites(): void
    {
        $this->streams->forget($this->streams->keys());
    }

    public function __get(string $name): mixed
    {
        return $this->ingest->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->ingest->{$name} = $value;
    }
}
