<?php

use Tests\FakeIngest;

use function Pest\Laravel\artisan;

it('fails when nightwatch is disabled', function () {
    nightwatch()->enabled = false;

    artisan('nightwatch:status')
        ->expectsOutputToContain('Nightwatch is disabled')
        ->assertExitCode(1);
});

it('fails when ingest throws an exception while pinging', function () {
    fakeIngest(new class extends FakeIngest
    {
        public function ping(): void
        {
            throw new RuntimeException('Whoops!');
        }
    });
    artisan('nightwatch:status')
        ->expectsOutputToContain('Whoops!')
        ->assertExitCode(1);
});

it('can ping', function () {
    fakeIngest();

    artisan('nightwatch:status')
        ->expectsOutputToContain('The Nightwatch agent is running and accepting connections')
        ->assertExitCode(0);
});
