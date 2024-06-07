<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nightwatch\PeakMemoryUsage;
use Laravel\Nightwatch\RecordCollection;
use Symfony\Component\HttpFoundation\Response;

class RequestSensor
{
    public function __construct(
        private RecordCollection $records,
        private PeakMemoryUsage $peakMemoryUsage,
    ) {
        //
    }

    public function __invoke(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $duration = (int) Carbon::now()->diffInMilliseconds($startedAt, true);

        $this->records['requests'][] = [
            'timestamp' => $startedAt->format('Y-m-d H:i:s'),
            // 'deploy_id' => '',
            // 'server' => '',
            // 'group' => hash('sha256', ''),
            // 'trace_id' => '',
            'method' => $request->getMethod(),
            'route' => '/'.$request->route()->uri(),
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
            ...$this->records['execution_parent'],
            // TODO: do we need to reset this in Octane, Queue worker, or other
            // long running processes?
            'peak_memory_usage_kilobytes' => (int) (($this->peakMemoryUsage)() / 1000),
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

        $this->records['alerts'][] = [
            // TODO need ot flesh this out more with info.
            'error' => 'code_here',
            'key' => 'response_size_kilobytes',
        ];

        return 0;
    }
}
