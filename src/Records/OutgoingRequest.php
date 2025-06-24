<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class OutgoingRequest extends Record
{
    public int $v = 1;

    public string $t = 'outgoing-request';

    /**
     * TODO limit string length
     *
     * @param  string|LazyValue<string>  $trace_id
     * @param  LazyValue<string>  $execution_id
     * @param  LazyValue<string>  $execution_preview
     * @param  string|LazyValue<string>  $user
     */
    public function __construct(
        private float $timestamp,
        private string $deploy,
        private string $server,
        private string $_group,
        private string|LazyValue $trace_id,
        private string $execution_source,
        private LazyValue $execution_id,
        private LazyValue $execution_preview,
        private ExecutionStage $execution_stage,
        private string|LazyValue $user,
        // --- /
        public readonly string $host, // grouping?
        public readonly string $method,
        public string $url,
        public readonly int $duration,
        public readonly int $request_size,
        public readonly int $response_size,
        public readonly int $status_code,
    ) {
        // $this->host = Str::tinyText($this->host);
        // $this->method = Str::tinyText($this->method);
        // $this->url = Str::text($this->url);
    }
}
