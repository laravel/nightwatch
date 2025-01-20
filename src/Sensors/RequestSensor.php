<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\State\RequestState;
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
        private RequestState $requestState,
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
            //
        }

        $this->requestState->records->write(new RequestRecord(
            timestamp: $this->requestState->timestamp,
            deploy: $this->requestState->deploy,
            server: $this->requestState->server,
            _group: hash('md5', implode('|', $routeMethods).",{$routeDomain},{$routePath}"),
            trace_id: $this->requestState->traceId,
            user: $this->requestState->user->id(),
            method: $request->getMethod(),
            url: $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().(strlen($query) > 0 ? "?{$query}" : ''),
            route_name: $route?->getName() ?? '',
            route_methods: $routeMethods,
            route_domain: $routeDomain,
            route_action: $route?->getActionName() ?? '',
            route_path: $routePath,
            ip: $request->ip() ?? '',
            duration: array_sum($this->requestState->stageDurations),
            status_code: $response->getStatusCode(),
            request_size: strlen($request->getContent()),
            response_size: $this->parseResponseSize($response),
            bootstrap: $this->requestState->stageDurations[ExecutionStage::Bootstrap->value],
            before_middleware: $this->requestState->stageDurations[ExecutionStage::BeforeMiddleware->value],
            action: $this->requestState->stageDurations[ExecutionStage::Action->value],
            render: $this->requestState->stageDurations[ExecutionStage::Render->value],
            after_middleware: $this->requestState->stageDurations[ExecutionStage::AfterMiddleware->value],
            sending: $this->requestState->stageDurations[ExecutionStage::Sending->value],
            terminating: $this->requestState->stageDurations[ExecutionStage::Terminating->value],
            exceptions: $this->requestState->exceptions,
            logs: $this->requestState->logs,
            queries: $this->requestState->queries,
            lazy_loads: $this->requestState->lazyLoads,
            jobs_queued: $this->requestState->jobsQueued,
            mail: $this->requestState->mail,
            notifications: $this->requestState->notifications,
            outgoing_requests: $this->requestState->outgoingRequests,
            files_read: $this->requestState->filesRead,
            files_written: $this->requestState->filesWritten,
            cache_events: $this->requestState->cacheEvents,
            hydrated_models: $this->requestState->hydratedModels,
            peak_memory_usage: $this->requestState->peakMemory(),
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
