<?php

namespace Tests;

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

    public function assertLatestWrite(array $payload): self
    {
        expect(count($this->writes))->toBeGreaterThan(0);

        expect($this->latestWrite())->toBe($payload);

        return $this;
    }

    public function latestWrite(): array
    {
        return json_decode($this->writes[0], true, flags: JSON_THROW_ON_ERROR);
    }
}
