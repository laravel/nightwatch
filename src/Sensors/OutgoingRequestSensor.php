<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\UserProvider;
use Psr\Http\Message\MessageInterface;
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
     */
    public function __invoke(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $duration = (int) round(($endMicrotime - $startMicrotime) * 1000);

        $this->executionParent->outgoing_requests++;
        $this->executionParent->outgoing_requests_duration += $duration;

        /** @var 'http'|'https' */
        $scheme = $request->getUri()->getScheme();

        $this->recordsBuffer->writeOutgoingRequest(new OutgoingRequest(
            timestamp: (int) $startMicrotime,
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($startMicrotime),
            user: $this->user->id(),
            method: $request->getMethod(),
            scheme: $scheme,
            host: $request->getUri()->getHost(),
            port: (string) ($request->getUri()->getPort() ?? match ($scheme) {
                'http' => 80,
                'https' => 443,
            }),
            path: $request->getUri()->getPath(),
            route: '',
            duration: $duration,
            request_size_kilobytes: $this->parseMessageSizeKilobytes($request),
            response_size_kilobytes: $this->parseMessageSizeKilobytes($response),
            status_code: (string) $response->getStatusCode(),
        ));
    }

    private function parseMessageSizeKilobytes(MessageInterface $message): ?int
    {
        $size = $message->getBody()->getSize();

        if ($size !== null) {
            return (int) round($size / 1000);
        }

        $length = $message->getHeader('content-length')[0] ?? null;

        if (is_numeric($length)) {
            return (int) round($length / 1000);
        }

        return null;
    }
}
