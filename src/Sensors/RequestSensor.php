<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\UserProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\Response;

use function array_sum;
use function hash;
use function implode;
use function is_int;
use function is_numeric;
use function is_string;
use function sort;
use function strlen;

/**
 * @internal
 */
final class RequestSensor
{
    public function __construct(
        private ExecutionState $executionState,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
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

        $port = (int) ($request->getPort() ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
        });

        $query = '';

        try {
            $query = $request->server->getString('QUERY_STRING');
        } catch (UnexpectedValueException $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        $this->recordsBuffer->writeRequest(new RequestRecord(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', implode('|', $routeMethods).",{$routeDomain},{$routePath}"),
            trace_id: $this->executionState->trace,
            user: $this->user->id(),
            method: $request->getMethod(),
            scheme: $scheme,
            url_user: $request->getUser() ?? '',
            host: $request->getHost(),
            port: $port,
            path: $request->getPathInfo(),
            query: $query,
            route_name: $route?->getName() ?? '',
            route_methods: $routeMethods,
            route_domain: $routeDomain,
            route_action: $route?->getActionName() ?? '',
            route_path: $routePath,
            ip: $request->ip() ?? '',
            duration: array_sum($this->executionState->stageDurations),
            status_code: $response->getStatusCode(),
            request_size: strlen($request->getContent()),
            response_size: $this->parseResponseSize($response),
            bootstrap: $this->executionState->stageDurations[ExecutionStage::Bootstrap->value],
            before_middleware: $this->executionState->stageDurations[ExecutionStage::BeforeMiddleware->value],
            action: $this->executionState->stageDurations[ExecutionStage::Action->value],
            render: $this->executionState->stageDurations[ExecutionStage::Render->value],
            after_middleware: $this->executionState->stageDurations[ExecutionStage::AfterMiddleware->value],
            sending: $this->executionState->stageDurations[ExecutionStage::Sending->value],
            terminating: $this->executionState->stageDurations[ExecutionStage::Terminating->value],
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
            peak_memory_usage: $this->executionState->peakMemory(),
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
