<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class PolyfillContextDehydration
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
        $context = Compatibility::$context;

        try {
            if (($context['nightwatch_user_id'] ?? '') === '') {
                $context['nightwatch_user_id'] = $this->nightwatch->executionState->user->resolvedUserId();
            }

            return [
                ...$payload,
                'nightwatch' => [
                    ...($payload['nightwatch'] ?? []), // @phpstan-ignore arrayUnpacking.nonIterable
                    'nightwatch_trace_id' => $context['nightwatch_trace_id'] ?? null,
                    'nightwatch_should_sample' => $context['nightwatch_should_sample'] ?? null,
                    'nightwatch_user_id' => $context['nightwatch_user_id'],
                ],
            ];
        } catch (Throwable $e) {
            $this->nightwatch->report($e);

            return $payload;
        }
    }
}
