<?php

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\NotificationSentListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function notification(NotificationSent $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $event = new NotificationSent(new stdClass, new stdClass, 'broadcast');

    $handler = new NotificationSentListener($nightwatch);

    $handler($event);

    expect($sensor->thrown)->toBeTrue();
});
