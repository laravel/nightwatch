<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;

/**
 * @internal
 */
final class OutgoingRequest
{
    public int $v = 1;

    public string $t = 'outgoing-request';

    /**
     * TODO limit string length
     *
     * @param  string|LazyValue<string>  $trace_id
     * @param  string|LazyValue<string>  $execution_id
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        public float $timestamp,
        public string $deploy,
        public string $server,
        public string $_group,
        public string|LazyValue $trace_id,
        public string $execution_source,
        public string|LazyValue $execution_id,
        public ExecutionStage $execution_stage,
        public string|LazyValue $user,
        // --- /
        public string $host,
        public string $method,
        public string $url,
        public int $duration,
        public int $request_size,
        public int $response_size,
        public int $status_code,
    ) {
        //
    }
}
