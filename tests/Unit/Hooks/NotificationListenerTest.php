<?php

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Hooks\NotificationListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function notification(NotificationSending|NotificationSent $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $event = new NotificationSent(new stdClass, new stdClass, 'broadcast');

    $handler = new NotificationListener($nightwatch);

    $handler($event);

    expect($sensor->thrown)->toBeTrue();
});
