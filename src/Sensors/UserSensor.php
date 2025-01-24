<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Records\User;
use Laravel\Nightwatch\State\RequestState;

final class UserSensor
{
    public function __construct(
        public RequestState $requestState,
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
            id: $details['id'],
            name: $details['name'],
            username: $details['username'],
        ));
    }
}
