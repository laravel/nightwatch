<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\UserProvider;
use Symfony\Component\HttpFoundation\Response;

final class RequestSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private PeakMemoryProvider $peakMemory,
        private UserProvider $user,
        private string $traceId,
        private string $deployId,
        private string $server,
    ) {
        //
    }

    /**
     * TODO group,
     * TODO when the request is a `resource`, calling `getContent` may re-read
     * the stream into memory. How can we handle this better?
     * TODO how can we better flag that a response is streamed and we don't
     * know the length?
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        $durationInMilliseconds = (int) round($startedAt->diffInMilliseconds());

        $this->recordsBuffer->writeRequest(new RequestRecord(
            timestamp: $startedAt->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            user: $this->user->id(),
            method: $request->getMethod(),
            route: with($request->route()?->uri(), fn (?string $uri) => $uri ? "/{$uri}" : ''), // @phpstan-ignore method.nonObject
            path: '/'.$request->path(),
            ip: $request->ip() ?? '',
            duration: $durationInMilliseconds,
            status_code: (string) $response->getStatusCode(),
            request_size_kilobytes: (int) round(
                ((int) ($request->headers->get('content-length') ?? strlen($request->getContent()))) / 1000
            ),
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
        if ($length = $response->headers->get('content-length')) {
            return (int) round(((int) $length) / 1000);
        }

        $content = $response->getContent();

        if (is_string($content)) {
            return (int) round(strlen($content) / 1000);
        }

        return 0;
    }
}
