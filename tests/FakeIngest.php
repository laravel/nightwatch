<?php

namespace Tests;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Records\Record;
use Laravel\Nightwatch\RecordsBuffer;
use PHPUnit\Framework\Assert;

use function collect;
use function count;
use function is_array;
use function json_decode;
use function str_contains;
use function value;

class FakeIngest implements Ingest
{
    /**
     * @var list<string>
     */
    public array $writes = [];

    public function __construct(
        public RecordsBuffer $buffer = new RecordsBuffer,
    ) {
        //
    }

    public function write(Record $record): void
    {
        $this->buffer->write($record);
    }

    public function digest(): void
    {
        $this->writes[] = $this->buffer->pull()->rawPayload();
    }

    public function ping(): void
    {
        //
    }

    public function flush(): void
    {
        $this->buffer->flush();
    }

    public function assertWrittenTimes(int $expected): self
    {
        Assert::assertSame($expected, $actual = count($this->writes), "Expected to have written [{$expected}]. Instead, was written [{$actual}].");

        return $this;
    }

    public function assertWrite(int $index, string|array|Closure $key, mixed $expected = null): self
    {
        Assert::assertGreaterThan($index, count($this->writes), 'Expected to have '.($index + 1).' writes. '.count($this->writes).' found.');

        $write = $this->decodedWrite($index);

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
        return $this->assertWrite(count($this->writes) - 1, $key, $expected);
    }

    public function latestWriteAsString(): ?string
    {
        return Arr::last($this->writes);
    }

    private function decodedWrite(int $index): mixed
    {
        return json_decode($this->writes[$index], true, flags: JSON_THROW_ON_ERROR);
    }
}
