<?php

namespace Tests;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\JobAttempt;
use Laravel\Nightwatch\Records\Log;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\Records\Notification;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;
use Laravel\Nightwatch\Records\ScheduledTask;
use Laravel\Nightwatch\Records\User;
use Laravel\Nightwatch\RecordsBuffer;
use RuntimeException;

use function collect;
use function count;
use function expect;
use function is_array;
use function json_decode;
use function str_contains;
use function value;

class FakeIngest implements LocalIngest
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

    public function write(Request|Command|Exception|CacheEvent|OutgoingRequest|Query|QueuedJob|JobAttempt|Mail|Notification|Log|User|ScheduledTask $record): void
    {
        $this->buffer->write($record);
    }

    public function digest(): void
    {
        $payload = $this->buffer->pull();

        if ($payload->isEmpty()) {
            throw new RuntimeException('The payload was empty.');
        }

        $this->writes[] = $payload->rawPayload();
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
        expect($actual = count($this->writes))->toBe($expected, "Expected to have written [{$expected}]. Instead, was written [{$actual}].");

        return $this;
    }

    public function assertWrite(int $index, string|array|Closure $key, mixed $expected = null): self
    {
        expect(count($this->writes))->toBeGreaterThan($index, 'Expected to have '.($index + 1).' writes. '.count($this->writes).' found.');

        $write = $this->decodedWrite($index);

        if ($key instanceof Closure) {
            [$key, $expected] = ['*', $key];
        }

        if (is_array($key)) {
            expect($write)->toBe($key, 'Failed asserting that the payload matched.');

            return $this;
        }

        if (str_contains($key, ':')) {
            $type = Str::before($key, ':');
            $key = Str::after($key, ':');

            $write = collect($write)->where('t', $type)->values()->all();
        }

        if ($key === '*') {
            if ($expected instanceof Closure) {
                expect($expected($write))->toBeTrue("The expected value was not found at [{$key}].");
            } else {
                expect($write)->toBe(value($expected, $write), "The expected value was not found at [{$key}].");
            }
        } else {
            expect(Arr::has($write, $key))->toBeTrue("The key [{$key}] does not exist in the latest write.");
            $actual = Arr::get($write, $key);

            if ($expected instanceof Closure) {
                expect($expected($actual))->toBeTrue("The expected value was not found at [{$key}].");
            } else {
                expect($actual)->toBe(value($expected, $actual), "The expected value was not found at [{$key}].");
            }
        }

        return $this;
    }

    public function assertLatestWrite(string|array|Closure $key, mixed $expected = null): self
    {
        return $this->assertWrite(count($this->writes) - 1, $key, $expected);
    }

    public function latestWriteAsString(): string
    {
        return Arr::last($this->writes);
    }

    private function decodedWrite(int $index): mixed
    {
        return json_decode($this->writes[$index], true, flags: JSON_THROW_ON_ERROR);
    }
}
