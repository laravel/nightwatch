<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
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
        private RequestState|CommandState $executionState,
    ) {
        //
    }

    /**
     * @return array{0: OutgoingRequest, 1: callable(): array<mixed>}
     */
    public function __invoke(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): array
    {
        $duration = (int) round(($endMicrotime - $startMicrotime) * 1_000_000);
        $uri = $request->getUri()->withUserInfo('', null);

        return [
            $record = new OutgoingRequest(
                method: $request->getMethod(),
                url: (string) $uri,
                duration: $duration,
                requestSize: $this->resolveMessageSize($request) ?? 0,
                responseSize: $this->resolveMessageSize($response) ?? 0,
                statusCode: $response->getStatusCode(),
            ),
            function () use ($startMicrotime, $uri, $record) {
                $this->executionState->outgoingRequests++;

                return [
                    'v' => 1,
                    't' => 'outgoing-request',
                    'timestamp' => $startMicrotime,
                    'deploy' => $this->executionState->deploy,
                    'server' => $this->executionState->server,
                    '_group' => hash('xxh128', $uri->getHost()),
                    'trace_id' => $this->executionState->trace,
                    'execution_source' => $this->executionState->source,
                    'execution_id' => $this->executionState->id(),
                    'execution_preview' => $this->executionState->executionPreview(),
                    'execution_stage' => $this->executionState->stage,
                    'user' => $this->executionState->user->id(),
                    // --- //
                    'host' => Str::tinyText($uri->getHost()),
                    'method' => Str::tinyText($record->method),
                    'url' => Str::text($record->url),
                    'duration' => $record->duration,
                    'request_size' => $record->requestSize,
                    'response_size' => $record->responseSize,
                    'status_code' => $record->statusCode,
                ];
            },
        ];
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
