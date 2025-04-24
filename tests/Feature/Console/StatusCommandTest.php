<?php

use Laravel\Nightwatch\Contracts\LocalIngest;

use function Pest\Laravel\artisan;

it('fails when nightwatch is disabled', function () {
    nightwatch()->enabled = false;

    artisan('nightwatch:status')
        ->expectsOutputToContain('Nightwatch is disabled')
        ->assertExitCode(1);
});

it('fails when ingest is unable to ping', function () {
    nightwatch()->ingest = new class implements LocalIngest
    {
        public function write(string $payload): void
        {
            //
        }

        public function ping(): bool
        {
            return false;
        }
    };
    artisan('nightwatch:status')
        ->expectsOutputToContain('Failed to check the status of the Nightwatch agent')
        ->assertExitCode(1);
});

it('fails when ingest throws an exception while pinging', function () {
    nightwatch()->ingest = new class implements LocalIngest
    {
        public function write(string $payload): void
        {
            //
        }

        public function ping(): bool
        {
            return throw new RuntimeException('Whoops!');
        }
    };
    artisan('nightwatch:status')
        ->expectsOutputToContain('Whoops!')
        ->assertExitCode(1);
});

it('can ping', function () {
    nightwatch()->ingest = new class implements LocalIngest
    {
        public function write(string $payload): void
        {
            //
        }

        public function ping(): bool
        {
            return true;
        }
    };
    artisan('nightwatch:status')
        ->expectsOutputToContain('The Nightwatch agent is running and accepting connections')
        ->assertExitCode(0);
});
