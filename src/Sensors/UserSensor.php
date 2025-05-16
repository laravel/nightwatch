<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Records\User;
use Laravel\Nightwatch\State\RequestState;

final class UserSensor
{
    public function __construct(
        private Ingest $ingest,
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

        $this->ingest->write(new User(
            timestamp: $this->clock->microtime(),
            id: (string) $details['id'], // @phpstan-ignore cast.string
            name: (string) ($details['name'] ?? ''), // @phpstan-ignore cast.string
            username: (string) ($details['username'] ?? ''), // @phpstan-ignore cast.string
        ));
    }
}
