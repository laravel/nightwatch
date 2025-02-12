<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\User;
use Laravel\Nightwatch\State\RequestState;

final class UserSensor
{
    public function __construct(
        private RequestState $requestState,
        public Clock $clock,
    ) {
        //
    }

    public function __invoke(): void
    {
        $details = $this->requestState->user->details();

        if ($details === null) {
            return;
        }

        $this->requestState->records->write(new User(
            timestamp: $this->clock->microtime(),
            id: $details['id'],
            name: $details['name'],
            username: $details['username'],
        ));
    }
}
