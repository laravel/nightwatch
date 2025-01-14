<?php

use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Hooks\JobPoppingListener;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    $ingest = fakeIngest();
    Str::createUuidsUsing(function () {
        throw new RuntimeException('Whoops!');
    });

    $handler = new JobPoppingListener(nightwatch());

    $handler(new JobPopping('redis', new FakeJob));

    expect(nightwatch()->state->exceptions)->toBe(1);
})->skip();
