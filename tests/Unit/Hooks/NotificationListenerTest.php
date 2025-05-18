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

    $this->assertTrue($thrownInNotificationSensor);
    $this->assertSame(1, nightwatch()->executionState->exceptions);
});
