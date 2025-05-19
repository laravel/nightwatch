<?php

namespace Tests\Unit\Hooks;

use Illuminate\Auth\Events\Logout;
use Laravel\Nightwatch\Hooks\LogoutListener;
use Tests\TestCase;

class LogoutListenerTest extends TestCase
{
    public function test_it_it_gracefully_handles_exceptions(): void
    {
        $event = new Logout('token', 'abc123');

        $listener = new LogoutListener($this->core);
        $listener($event);

        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
