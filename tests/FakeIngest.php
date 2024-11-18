<?php

namespace Tests;

use Closure;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Contracts\LocalIngest;

use function count;
use function expect;
use function is_array;
use function json_decode;

final class FakeIngest implements LocalIngest
{
    /**
     * @var list<string>
     */
    public array $writes = [];

    public function write(string $payload): void
    {
        $this->writes[] = $payload;
    }

    public function assertWrittenTimes(int $expected): self
    {
        expect($actual = count($this->writes))->toBe($expected, "Expected to have written [{$expected}]. Instead, was written [{$expected}].");

        return $this;
    }

    public function assertLatestWrite(string|array $key, mixed $payload = null): self
    {
        expect(count($this->writes))->toBeGreaterThan(0, 'Expected to have writes. None found.');

        [$key, $payload] = is_array($key)
            ? [null, $key]
            : [$key, $payload];

        if ($payload instanceof Closure) {
            expect($payload($this->latestWrite($key)))->toBeTrue($key ? "Failed asserting [{$key}] was expected value." : '');
        } else {
            expect($this->latestWrite($key))->toBe($payload, $key ? "Failed asserting [{$key}] was expected value." : '');
        }

        return $this;
    }

    public function latestWriteAsString(): string
    {
        return Arr::last($this->writes);
    }

    public function latestWrite(?string $key = null): mixed
    {
        $payload = json_decode(Arr::last($this->writes), true, flags: JSON_THROW_ON_ERROR);

        if ($key) {
            expect(Arr::has($payload, $key))->toBeTrue("The payload does not contain the key [{$key}].");

            return Arr::get($payload, $key, null);
        } else {
            return $payload;
        }
    }
}
