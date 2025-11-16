<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class CreateQueuePayloadHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(mixed $connection, mixed $queue, array $payload): array
    {
        try {
            return [
                ...$payload,
                'nightwatch' => [
                    ...($payload['nightwatch'] ?? []),  // @phpstan-ignore arrayUnpacking.nonIterable
                    'job_id' => $this->nightwatch->uuid->make(),
                ],
            ];
        } catch (Throwable $e) {
            $this->nightwatch->report($e);

            return $payload;
        }
    }
}
