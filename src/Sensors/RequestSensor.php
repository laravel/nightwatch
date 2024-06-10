<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\RecordCollection;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Symfony\Component\HttpFoundation\Response;

final class RequestSensor
{
    public function __construct(
        private RecordCollection $records,
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

        $this->records['requests'][] = new RequestRecord(
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
            queries: $this->records['execution_parent']['queries'],
            queries_duration: $this->records['execution_parent']['queries_duration'],
            lazy_loads: $this->records['execution_parent']['lazy_loads'],
            lazy_loads_duration: $this->records['execution_parent']['lazy_loads_duration'],
            jobs_queued: $this->records['execution_parent']['jobs_queued'],
            mail_queued: $this->records['execution_parent']['mail_queued'],
            mail_sent: $this->records['execution_parent']['mail_sent'],
            mail_duration: $this->records['execution_parent']['mail_duration'],
            notifications_queued: $this->records['execution_parent']['notifications_queued'],
            notifications_sent: $this->records['execution_parent']['notifications_sent'],
            notifications_duration: $this->records['execution_parent']['notifications_duration'],
            outgoing_requests: $this->records['execution_parent']['outgoing_requests'],
            outgoing_requests_duration: $this->records['execution_parent']['outgoing_requests_duration'],
            files_read: $this->records['execution_parent']['files_read'],
            files_read_duration: $this->records['execution_parent']['files_read_duration'],
            files_written: $this->records['execution_parent']['files_written'],
            files_written_duration: $this->records['execution_parent']['files_written_duration'],
            cache_hits: $this->records['execution_parent']['cache_hits'],
            cache_misses: $this->records['execution_parent']['cache_misses'],
            hydrated_models: $this->records['execution_parent']['hydrated_models'],
            peak_memory_usage_kilobytes: $this->peakMemory->kilobytes(),
        );
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
