<?php

namespace Laravel\Nightwatch\Sensors;

use DateTimeInterface;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\RecordCollection;
use Laravel\Nightwatch\TraceId;
use Symfony\Component\HttpFoundation\Response;

final class RequestSensor
{
    public function __construct(
        private RecordCollection $records,
        private PeakMemoryProvider $peakMemory,
        private TraceId $traceId,
        private Config $config,
    ) {
        //
    }

    public function __invoke(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $duration = (int) Carbon::now()->diffInMilliseconds($startedAt, true);

        $this->records['requests'][] = [
            'timestamp' => $startedAt->format('Y-m-d H:i:s'),
            'deploy_id' => (string) $this->config->get('nightwatch.deploy_id'),
            'server' => (string) $this->config->get('nightwatch.server'),
            'group' => hash('sha256', ''),  // TODO
            'trace_id' => $this->traceId->value(),
            // TODO domain as individual key?
            'method' => $request->getMethod(),
            'route' => '/'.$request->route()->uri(), // TODO handle nullable routes.
            'path' => '/'.$request->path(),
            'user' => '',
            'ip' => $request->ip(),
            'duration' => $duration,
            'status_code' => (string) $response->getStatusCode(),
            // Although we usually should not trust random header input, it
            // seems that the header input is respected by web servers and PHP,
            // so we should be able to trust this if it exists. In some cases
            // it is even required in order to indicate the entire request has
            // been received.
            'request_size_kilobytes' => (int) (
                // TODO test how this handles:
                // - chunked requests
                // - Content-Encoding requests
                // are there potential memory issues if the body is a resource
                // and not a string?
                $request->headers->get('content-length') ?? strlen($request->getContent())
            ),
            // TODO test how this handles:
            // - chunked requests
            // - Content-Encoding requests
            'response_size_kilobytes' => $this->parseResponseKilobytes($response),
            'peak_memory_usage_kilobytes' => $this->peakMemory->kilobytes(),
            ...$this->records['execution_parent'],
            // TODO: do we need to reset this in Octane, Queue worker, or other
            // long running processes?
        ];
    }

    private function parseResponseKilobytes(Response $response): int
    {
        // chunked responses...
        if ($response->headers->has('content-length')) {
            return (int) $response->headers->get('content-length');
        }

        // normal requests...
        $content = $response->getContent();

        if ($content !== false) {
            return strlen($content);
        }

        // Something bad happened...

        // $this->records['alerts'][] = [
        //     // TODO need ot flesh this out more with info.
        //     'error' => 'code_here',
        //     'key' => 'response_size_kilobytes',
        // ];

        return 0;
    }
}
