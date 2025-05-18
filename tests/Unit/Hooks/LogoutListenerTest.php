<?php

use Illuminate\Auth\Events\Logout;
use Laravel\Nightwatch\Hooks\LogoutListener;

test('it gracefully handles exceptions', function () {
    $event = new Logout('token', 'abc123');

    $listener = new LogoutListener(nightwatch());
    $listener($event);

    $this->assertSame(1, nightwatch()->executionState->exceptions);
});
