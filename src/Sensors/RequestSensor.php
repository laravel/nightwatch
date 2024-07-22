<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\LifecyclePhase;
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
     * @param  array<value-of<LifecyclePhase>, float>  $lifecycle
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
        private array $lifecycle,
    ) {
        //
    }

    public function __invoke(Request $request, Response $response): void
    {
        $nowMicrotime = $this->clock->microtime();
        /** @var Route|null */
        $route = $request->route();
        /** @var string|null */
        $routeUri = $route?->uri();
        /** @var 'http'|'https' */
        $scheme = $request->getScheme();
        /** @var list<string> */
        $methods = $route?->methods() ?? [];
        sort($methods);

        $this->recordsBuffer->writeRequest(new RequestRecord(
            timestamp: $this->clock->executionStartMicrotime(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', implode(',', $methods)),
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
            query: $request->server->get('QUERY_STRING'),
            route_name: $route?->getName() ?? '',
            route_methods: $methods,
            route_domain: $route?->getDomain() ?? '',
            route_action: $route?->getActionName() ?? '',
            route_path: $routeUri === null ? '' : "/{$routeUri}",
            ip: $request->ip() ?? '',
            duration: (int) (($nowMicrotime - $this->clock->executionStartMicrotime()) * 1000 * 1000),
            status_code: (string) $response->getStatusCode(),
            request_size: strlen($request->getContent()),
            response_size: $this->parseResponseSizeKilobytes($response),
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
            global_before_middleware: $this->lifecycle[LifecyclePhase::GlobalBeforeMiddleware->value] ?? 0,
            route_before_middleware: $this->lifecycle[LifecyclePhase::RouteBeforeMiddleware->value] ?? 0,
            main: $this->lifecycle[LifecyclePhase::Main->value] ?? 0,
            main_render: $this->lifecycle[LifecyclePhase::MainRender->value] ?? 0,
            route_after_middleware: $this->lifecycle[LifecyclePhase::RouteAfterMiddleware->value] ?? 0,
            route_after_middleware_render: $this->lifecycle[LifecyclePhase::RouteAfterMiddlewareRender->value] ?? 0,
            global_after_middleware: $this->lifecycle[LifecyclePhase::GlobalAfterMiddleware->value] ?? 0,
            response_transmission: $this->lifecycle[LifecyclePhase::ResponseTransmission->value] ?? 0,
            terminate: $this->lifecycle[LifecyclePhase::Terminate->value] ?? 0,
        ));
    }

    private function parseResponseSizeKilobytes(Response $response): ?int
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
