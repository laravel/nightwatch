<?php

namespace Laravel\Nightwatch\Records;

use Laravel\Nightwatch\Types\Text;

final class OutgoingRequest
{
    public int $v = 1;

    public function __construct(
        public string $timestamp,
        public string $deploy_id,
        public string $server,
        public string $group,
        public string $trace_id,
        public string $execution_context,
        public string $execution_id,
        public int $execution_offset,
        public string $user,
        // --- /
        public string $method,
        public string $url,
        public int $duration,
        public int $request_size_kilobytes,
        public int $response_size_kilobytes,
        public string $status_code,
    ) {
        $this->url = Text::limit($this->url);
    }
}
