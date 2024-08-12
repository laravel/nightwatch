<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
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
     * @param  array<value-of<ExecutionStage>, int>  $executionStages
     */
    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
        private PeakMemoryProvider $peakMemory,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
        private array $executionStages,
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
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', implode(',', [implode('|', $routeMethods), $routeDomain, $routePath])),
            trace_id: $this->executionState->trace,
            user: $this->user->id(),
            method: $request->getMethod(),
            scheme: $scheme,
            url_user: $request->getUser() ?? '',
            host: $request->getHost(),
            port: (int) ($request->getPort() ?? match ($scheme) {
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
            duration: array_sum($this->executionStages),
            status_code: $response->getStatusCode(),
            request_size: strlen($request->getContent()),
            response_size: $this->parseResponseSize($response),
            bootstrap: $this->executionStages[ExecutionStage::Bootstrap->value] ?? 0,
            before_middleware: $this->executionStages[ExecutionStage::BeforeMiddleware->value] ?? 0,
            action: $this->executionStages[ExecutionStage::Action->value] ?? 0,
            render: $this->executionStages[ExecutionStage::Render->value] ?? 0,
            after_middleware: $this->executionStages[ExecutionStage::AfterMiddleware->value] ?? 0,
            sending: $this->executionStages[ExecutionStage::Sending->value] ?? 0,
            terminating: $this->executionStages[ExecutionStage::Terminating->value] ?? 0,
            exceptions: $this->executionState->exceptions,
            queries: $this->executionState->queries,
            lazy_loads: $this->executionState->lazy_loads,
            jobs_queued: $this->executionState->jobs_queued,
            mail_queued: $this->executionState->mail_queued,
            mail_sent: $this->executionState->mail_sent,
            notifications_queued: $this->executionState->notifications_queued,
            notifications_sent: $this->executionState->notifications_sent,
            outgoing_requests: $this->executionState->outgoing_requests,
            files_read: $this->executionState->files_read,
            files_written: $this->executionState->files_written,
            cache_hits: $this->executionState->cache_hits,
            cache_misses: $this->executionState->cache_misses,
            hydrated_models: $this->executionState->hydrated_models,
            peak_memory_usage: $this->peakMemory->bytes(),
        ));
    }

    private function parseResponseSize(Response $response): int
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

        // TODO We are unable to determine the size of the response. We will
        // set this to `0`. We should offer a way to tell us the size of the
        // streamed response, e.g., echo Nightwatch::streaming($content);
        return 0;
    }
}
