<?php

namespace Tests\Unit\Hooks;

use Illuminate\Notifications\Events\NotificationSent;
use Laravel\Nightwatch\Hooks\NotificationListener;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class NotificationListenerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInNotificationSensor = false;
        $this->core->sensor->notificationSensor = function () use (&$thrownInNotificationSensor): void {
            $thrownInNotificationSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $event = new NotificationSent(new stdClass, new stdClass, 'broadcast');

        $handler = new NotificationListener($this->core);
        $handler($event);

        $this->assertTrue($thrownInNotificationSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
