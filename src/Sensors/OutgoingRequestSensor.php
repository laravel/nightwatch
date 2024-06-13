<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\OutgoingRequest;

final class OutgoingRequestSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(float $start, float $duration, Request $request, Response $response): void
    {
        $duration = round($duration * 1000);

        $this->recordsBuffer->writeOutgoingRequest(new OutgoingRequest(

            timestamp: CarbonImmutable::parse($start, 'UTC')->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),  // TODO
            trace_id: $this->traceId,
            execution_context: 'request', // TODO
            execution_id: '00000000-0000-0000-0000-000000000000', // TODO
            user: Auth::id() ?? '', // TODO: allow this to be customised
            method: $request->getMethod(),
            url: (string) $request->getUri(),
            duration: $duration,
            request_size_kilobytes: round(
                // TODO test how this handles:
                // - chunked requests
                // - Content-Encoding requests
                // are there potential memory issues if the body is a resource. Could be a lot.
                ($request->getHeader('content-length')[0] ?? $request->getBody()->getSize() ?? strlen((string) $request->getBody())) / 1000
            ),
            response_size_kilobytes: round(
                // TODO: we might be reading a stream into memory here. We need to improve this.
                ($response->getHeader('content-length')[0] ?? $response->getBody()->getSize() ?? strlen((string) $response->getBody())) / 1000
            ),
            status_code: (string) $response->getStatusCode(),
        ));

        $this->executionParent->outgoing_requests++;
        $this->executionParent->outgoing_requests_duration = $duration;
    }
}
