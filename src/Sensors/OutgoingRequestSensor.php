<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeZone;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\UserProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class OutgoingRequestSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private UserProvider $user,
        private Clock $clock,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, route
     * TODO test against streamed requests / responses.
     * TODO decide how to handle streams where we do not know the payload size.
     * TODO It seems like `getSize` may throw an exception in some cases. We may need to `rescue`.
     */
    public function __invoke(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $duration = (int) round(($endMicrotime - $startMicrotime) * 1000);

        $this->recordsBuffer->writeOutgoingRequest(new OutgoingRequest(
            timestamp: DateTime::createFromFormat('U', (int) $startMicrotime, new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($startMicrotime),
            user: $this->user->id(),
            method: $request->getMethod(),
            scheme: $request->getUri()->getScheme(),
            host: $request->getUri()->getHost(),
            port: (string) $request->getUri()->getPort(),
            path: $request->getUri()->getPath(),
            route: '',
            duration: $duration,
            request_size_kilobytes: (int) round(
                ((int) ($request->getHeader('content-length')[0] ?? $request->getBody()->getSize() ?? 0)) / 1000
            ),
            response_size_kilobytes: (int) round(
                ((int) ($response->getHeader('content-length')[0] ?? $response->getBody()->getSize() ?? 0)) / 1000
            ),
            status_code: (string) $response->getStatusCode(),
        ));

        $this->executionParent->outgoing_requests++;
        $this->executionParent->outgoing_requests_duration += $duration;
    }
}
