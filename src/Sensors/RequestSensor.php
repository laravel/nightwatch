<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Symfony\Component\HttpFoundation\Response;

final class RequestSensor
{
    public function __construct(
        private Records $records,
        private ExecutionParent $executionParent,
        private PeakMemoryProvider $peakMemory,
        private string $traceId,
        private string $deployId,
        private string $server,
    ) {
        //
    }

    public function __invoke(DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $duration = (int) CarbonImmutable::now()->diffInMilliseconds($startedAt, true); // TODO: can I do this without using Carbon?

        $this->records->addRequest(new RequestRecord(
            timestamp: $startedAt->format('Y-m-d H:i:s'),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),  // TODO
            trace_id: $this->traceId,
            // TODO domain as individual key?
            method: $request->getMethod(),
            route: '/'.$request->route()->uri(), // TODO handle nullable routes.
            path: '/'.$request->path(),
            user: '',
            ip: $request->ip(), // TODO: can be nullable
            duration: $duration,
            status_code: (string) $response->getStatusCode(),
            // Although we usually should not trust random header input, it
            // seems that the header input is respected by web servers and PHP,
            // so we should be able to trust this if it exists. In some cases
            // it is even required in order to indicate the entire request has
            // been received.
            request_size_kilobytes: (int) (
                // TODO test how this handles:
                // - chunked requests
                // - Content-Encoding requests
                // are there potential memory issues if the body is a resource
                // and not a string?
                ($request->headers->get('content-length') ?? strlen($request->getContent())) / 1000
            ),
            // TODO test how this handles:
            // - chunked requests
            // - Content-Encoding requests
            response_size_kilobytes: $this->parseResponseSizeKilobytes($response),
            queries: $this->executionParent->queries,
            queries_duration: $this->executionParent->queries_duration,
            lazy_loads: $this->executionParent->lazy_loads,
            lazy_loads_duration: $this->executionParent->lazy_loads_duration,
            jobs_queued: $this->executionParent->jobs_queued,
            mail_queued: $this->executionParent->mail_queued,
            mail_sent: $this->executionParent->mail_sent,
            mail_duration: $this->executionParent->mail_duration,
            notifications_queued: $this->executionParent->notifications_queued,
            notifications_sent: $this->executionParent->notifications_sent,
            notifications_duration: $this->executionParent->notifications_duration,
            outgoing_requests: $this->executionParent->outgoing_requests,
            outgoing_requests_duration: $this->executionParent->outgoing_requests_duration,
            files_read: $this->executionParent->files_read,
            files_read_duration: $this->executionParent->files_read_duration,
            files_written: $this->executionParent->files_written,
            files_written_duration: $this->executionParent->files_written_duration,
            cache_hits: $this->executionParent->cache_hits,
            cache_misses: $this->executionParent->cache_misses,
            hydrated_models: $this->executionParent->hydrated_models,
            peak_memory_usage_kilobytes: $this->peakMemory->kilobytes(),
        ));
    }

    private function parseResponseSizeKilobytes(Response $response): int
    {
        // chunked responses...
        if ($length = $response->headers->get('content-length')) {
            return (int) ($length / 1000);
        }

        // normal requests...
        $content = $response->getContent();

        if ($content !== false) {
            return (int) (strlen($content) / 1000);
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
