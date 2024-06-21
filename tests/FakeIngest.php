<?php

namespace Tests;

use Illuminate\Support\Arr;
use Laravel\Nightwatch\Contracts\Ingest;

final class FakeIngest implements Ingest
{
    /**
     * @var list<string>
     */
    public array $writes = [];

    public function write(string $payload): void
    {
        $this->writes[] = $payload;
    }

    public function assertWrittenTimes(int $times): self
    {
        expect($this->writes)->toHaveCount($times);

        return $this;
    }

    public function assertLatestWrite(string|array $key, mixed $payload = null): self
    {
        expect(count($this->writes))->toBeGreaterThan(0);

        [$key, $payload] = is_array($key)
            ? [null, $key]
            : [$key, $payload];

        expect($this->latestWrite($key))->toBe($payload);

        return $this;
    }

    public function latestWrite(?string $key = null): mixed
    {
        $payload = json_decode($this->writes[0], true, flags: JSON_THROW_ON_ERROR);

        if ($key) {
            expect(Arr::has($payload, $key))->toBeTrue();

            return Arr::get($payload, $key, null);
        } else {
            return $payload;
        }
    }
}
