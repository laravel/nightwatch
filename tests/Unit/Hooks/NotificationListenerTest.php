<?php

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Hooks\NotificationListener;

it('gracefully handles exceptions', function () {
    $thrownInNotificationSensor = false;
    nightwatch()->sensor->notificationSensor = function () use (&$thrownInNotificationSensor) {
        $thrownInNotificationSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $event = new NotificationSent(new stdClass, new stdClass, 'broadcast');

    $handler = new NotificationListener(nightwatch());
    $handler($event);

    expect($thrownInNotificationSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
