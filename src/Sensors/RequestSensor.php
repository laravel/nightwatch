<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\ExecutionPhase;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\UserProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class RequestSensor
{
    /**
     * @param  array<value-of<ExecutionPhase>, int>  $executionPhases
     */
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private PeakMemoryProvider $peakMemory,
        private UserProvider $user,
        private Clock $clock,
        private string $traceId,
        private string $deployId,
        private string $server,
        private array $executionPhases,
    ) {
        //
    }

    public function __invoke(Request $request, Response $response): void
    {
        /** @var Route|null */
        $route = $request->route();
        /** @var 'http'|'https' */
        $scheme = $request->getScheme();
        /** @var list<string> */
        $routeMethods = $route?->methods() ?? [];
        sort($routeMethods);
        $routeDomain = $route?->getDomain() ?? '';
        $routePath = match ($routeUri = $route?->uri()) {
            null => '',
            '/' => '/',
            default => "/{$routeUri}",
        };

        $this->recordsBuffer->writeRequest(new RequestRecord(
            timestamp: $this->clock->executionStartInMicrotime(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('md5', implode(',', [implode('|', $routeMethods), $routeDomain, $routePath])),
            trace_id: $this->traceId,
            user: $this->user->id(),
            method: $request->getMethod(),
            scheme: $scheme,
            url_user: $request->getUser() ?? '',
            host: $request->getHost(),
            port: (string) ($request->getPort() ?? match ($scheme) {
                'http' => 80,
                'https' => 443,
            }),
            path: $request->getPathInfo(),
            query: rescue(fn () => $request->server->getString('QUERY_STRING'), '', report: false),
            route_name: $route?->getName() ?? '',
            route_methods: $routeMethods,
            route_domain: $routeDomain,
            route_action: $route?->getActionName() ?? '',
            route_path: $routePath,
            ip: $request->ip() ?? '',
            duration: array_sum($this->executionPhases),
            status_code: (string) $response->getStatusCode(),
            request_size: strlen($request->getContent()),
            response_size: $this->parseResponseSize($response),
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
            peak_memory_usage: $this->peakMemory->bytes(),
            bootstrap: $this->executionPhases[ExecutionPhase::Bootstrap->value] ?? 0,
            before_middleware: $this->executionPhases[ExecutionPhase::BeforeMiddleware->value] ?? 0,
            action: $this->executionPhases[ExecutionPhase::Action->value] ?? 0,
            render: $this->executionPhases[ExecutionPhase::Render->value] ?? 0,
            after_middleware: $this->executionPhases[ExecutionPhase::AfterMiddleware->value] ?? 0,
            sending: $this->executionPhases[ExecutionPhase::Sending->value] ?? 0,
            terminating: $this->executionPhases[ExecutionPhase::Terminating->value] ?? 0,
        ));
    }

    private function parseResponseSize(Response $response): ?int
    {
        if (is_string($content = $response->getContent())) {
            return strlen($content);
        }

        if ($response instanceof BinaryFileResponse) {
            try {
                if (is_int($size = $response->getFile()->getSize())) {
                    return $size;
                }
            } catch (RuntimeException $e) {
                //
            }
        }

        if (is_numeric($length = $response->headers->get('content-length'))) {
            return (int) $length;
        }

        return null;
    }
}
