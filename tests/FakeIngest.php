<?php

namespace Tests;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Contracts\LocalIngest;

use function collect;
use function count;
use function expect;
use function is_array;
use function json_decode;
use function str_contains;
use function value;

final class FakeIngest implements LocalIngest
{
    /**
     * @var list<string>
     */
    public array $writes = [];

    public function write(string $payload): void
    {
        if (strlen($payload) === 0) {
            throw new \RuntimeException('The payload was empty.');
        }
        $this->writes[] = $payload;
    }

    public function assertWrittenTimes(int $expected): self
    {
        expect($actual = count($this->writes))->toBe($expected, "Expected to have written [{$expected}]. Instead, was written [{$actual}].");

        return $this;
    }

    public function assertLatestWrite(string|array $key, mixed $expected = null): self
    {
        expect(count($this->writes))->toBeGreaterThan(0, 'Expected to have writes. None found.');

        $latestWrite = $this->latestWrite();

        if (is_array($key)) {
            expect($latestWrite)->toBe($key, 'Failed asserting that the payload matched.');

            return $this;
        }

        if (str_contains($key, ':')) {
            $type = Str::before($key, ':');

            $latestWrite = collect($latestWrite)->where('t', $type)->values()->all();

            expect(count($latestWrite) > 0)->toBeTrue('The type was not found in the latest write.');

            $key = Str::after($key, ':');
        }

        if ($key === '*') {
            if ($expected instanceof Closure) {
                expect($expected($latestWrite))->toBeTrue("The expected value was not found at [{$key}].");
            } else {
                expect($latestWrite)->toBe(value($expected, $latestWrite), "The expected value was not found at [{$key}].");
            }
        } else {
            expect(Arr::has($latestWrite, $key))->toBeTrue("The key [{$key}] does not exist in the latest write.");
            $actual = Arr::get($latestWrite, $key);

            if ($expected instanceof Closure) {
                expect($expected($actual))->toBeTrue("The expected value was not found at [{$key}].");
            } else {
                expect($actual)->toBe(value($expected, $actual), "The expected value was not found at [{$key}].");
            }
        }

        return $this;
    }

    public function latestWriteAsString(): string
    {
        return Arr::last($this->writes);
    }

    private function latestWrite(): mixed
    {
        return json_decode(Arr::last($this->writes), true, flags: JSON_THROW_ON_ERROR);
    }
}
