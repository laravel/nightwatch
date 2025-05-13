<?php

use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobAttempted;
use Laravel\Nightwatch\Hooks\JobAttemptedListener;
use Tests\FakeJob;

it('gracefully handles exceptions', function () {
    fakeIngest();
    $thrownInJobAttemptSensor = false;
    nightwatch()->sensor->jobAttemptSensor = function () use (&$thrownInJobAttemptSensor) {
        $thrownInJobAttemptSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $event = new JobAttempted('redis', new FakeJob);

    $handler = new JobAttemptedListener(nightwatch());
    $handler($event);

    expect($thrownInJobAttemptSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
})->skip(version_compare(Application::VERSION, '11.0.0', '<'), 'Laravel 10 support is pending');
