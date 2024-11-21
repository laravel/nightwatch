<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\UserProvider;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function hash;
use function is_numeric;
use function round;

/**
 * @internal
 */
final class OutgoingRequestSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionState $executionState,
        private UserProvider $user,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, route
     */
    public function __invoke(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
    {
        $duration = (int) round(($endMicrotime - $startMicrotime) * 1_000_000);

        /** @var 'http'|'https' */
        $scheme = $request->getUri()->getScheme();

        $port = (string) ($request->getUri()->getPort() ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
        });

        $this->executionState->outgoing_requests++;

        $this->recordsBuffer->write(new OutgoingRequest(
            timestamp: $startMicrotime,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            group: hash('sha256', ''),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            user: $this->user->id(),
            method: $request->getMethod(),
            scheme: $scheme,
            host: $request->getUri()->getHost(),
            port: $port,
            path: $request->getUri()->getPath(),
            duration: $duration,
            request_size: $this->resolveMessageSize($request) ?? 0,
            response_size: $this->resolveMessageSize($response) ?? 0,
            status_code: (string) $response->getStatusCode(),
        ));
    }

    private function resolveMessageSize(MessageInterface $message): ?int
    {
        $size = $message->getBody()->getSize();

        if ($size !== null) {
            return $size;
        }

        $length = $message->getHeader('content-length')[0] ?? null;

        if (is_numeric($length)) {
            return (int) $length;
        }

        return null;
    }
}
