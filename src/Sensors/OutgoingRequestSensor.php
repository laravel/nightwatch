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

    /**
     * TODO group, execution_context, execution_id
     * TODO allow auth to be customised? Inject auth manager into class.
     * TODO test against checked requests / responses.
     * TODO decide how to handle streams where we do not know the payload size.
     * TODO It seems like `getSize` may throw an exception in some cases. We may need to `rescue`.
     */
    public function __invoke(float $startInMicrotime, float $durationInMicrotime, Request $request, Response $response): void
    {
        $durationInMilliseconds = (int) round($durationInMicrotime * 1000);

        $this->recordsBuffer->writeOutgoingRequest(new OutgoingRequest(
            timestamp: CarbonImmutable::parse($startInMicrotime, 'UTC')->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            user: (string) Auth::id(),
            method: $request->getMethod(),
            url: (string) $request->getUri(),
            duration: $durationInMilliseconds,
            request_size_kilobytes: (int) round(
                ($request->getHeader('content-length')[0] ?? $request->getBody()->getSize() ?? 0) / 1000
            ),
            response_size_kilobytes: (int) round(
                ($response->getHeader('content-length')[0] ?? $response->getBody()->getSize() ?? 0) / 1000
            ),
            status_code: (string) $response->getStatusCode(),
        ));

        $this->executionParent->outgoing_requests++;
        $this->executionParent->outgoing_requests_duration += $durationInMilliseconds;
    }
}
