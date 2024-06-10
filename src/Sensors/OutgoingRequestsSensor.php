<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\RecordCollection;
use Laravel\Nightwatch\Records\OutgoingRequest;

final class OutgoingRequestsSensor
{
    public function __construct(
        private RecordCollection $records,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $duration = (int) CarbonImmutable::now()->diffInMilliseconds($startedAt, true); // TODO: can I do this without using Carbon?

        $this->records['outgoing_requests'][] = new OutgoingRequest(
            timestamp: $startedAt->format('Y-m-d H:i:s'), // TODO make sure this is when the request started, not ended.
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),  // TODO
            trace_id: $this->traceId,
            execution_context: 'request', // TODO
            execution_id: '00000000-0000-0000-0000-000000000000', // TODO
            user: Auth::id() ?? '', // TODO: allow this to be customised
            method: $request->getMethod(),
            url: $request->getUri(),
            duration: $duration,
            request_size_kilobytes: (int) (
               // TODO test how this handles:
               // - chunked requests
               // - Content-Encoding requests
               // are there potential memory issues if the body is a resource. Could be a lot.
               ($request->getHeader('content-length')[0] ?? $request->getBody()->getSize() ?? strlen((string) $request->getBody())) / 1000
            ),
            response_size_kilobytes: (int) (
               ($response->getHeader('content-length')[0] ?? $response->getBody()->getSize() ?? strlen((string) $response->getBody())) / 1000
            ),
            status_code: (string) $response->getStatusCode(),
        );

        $executionParent = $this->records['execution_parent'];

        $executionParent['outgoing_requests'] += 1;
        $executionParent['outgoing_requests_duration'] += $duration;
    }
}
