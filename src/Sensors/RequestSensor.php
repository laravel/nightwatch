<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\UserProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        private UserProvider $user,
    ) {
        //
    }

    public function __invoke(Request $request, Response $response): void
    {
        /** @var Route|null */
        $route = $request->route();

        /** @var list<string> */
        $routeMethods = $route?->methods() ?? [];

        sort($routeMethods);

        $routeDomain = $route?->getDomain() ?? '';

        $routePath = match ($routeUri = $route?->uri()) {
            null => '',
            '/' => '/',
            default => "/{$routeUri}",
        };

        $query = '';

        try {
            $query = $request->server->getString('QUERY_STRING');
        } catch (UnexpectedValueException $e) {
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        $this->executionState->records->write(new RequestRecord(
            timestamp: $this->executionState->timestamp,
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', implode('|', $routeMethods).",{$routeDomain},{$routePath}"),
            trace_id: $this->executionState->trace,
            user: $this->user->id(),
            method: $request->getMethod(),
            url: $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().(strlen($query) > 0 ? "?{$query}" : ''),
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
            logs: $this->executionState->logs,
            queries: $this->executionState->queries,
            lazy_loads: $this->executionState->lazy_loads,
            jobs_queued: $this->executionState->jobs_queued,
            mail: $this->executionState->mail,
            notifications: $this->executionState->notifications,
            outgoing_requests: $this->executionState->outgoing_requests,
            files_read: $this->executionState->files_read,
            files_written: $this->executionState->files_written,
            cache_events: $this->executionState->cache_events,
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
            } catch (Throwable $e) {
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
