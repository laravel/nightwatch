<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Nightwatch\Concerns\RecordsContext;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
    use RecordsContext;

    public function __construct(
        private RequestState $requestState,
    ) {
        //
    }

    /**
     * @return array{0: RequestRecord, 1: callable(): array<mixed>}
     */
    public function __invoke(Request $request, Response $response): array
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
            $query = (string) $request->server->get('QUERY_STRING'); // @phpstan-ignore cast.string
        } catch (Throwable) {
            //
        }

        return [
            $record = new RequestRecord(
                method: $request->getMethod(),
                url: $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().(strlen($query) > 0 ? "?{$query}" : ''),
                routeName: $route?->getName() ?? '',
                routeMethods: $routeMethods,
                routeDomain: $routeDomain,
                routePath: $routePath,
                routeAction: $this->requestState->routeAction ?? $route?->getActionName() ?? '',
                ip: $request->ip() ?? '',
                duration: array_sum($this->requestState->stageDurations),
                statusCode: $response->getStatusCode(),
                requestSize: strlen($request->getContent()),
                responseSize: $this->parseResponseSize($response),
            ),
            function () use ($record) {
                return [
                    'v' => 1,
                    't' => 'request',
                    'timestamp' => $this->requestState->timestamp,
                    'deploy' => $this->requestState->deploy,
                    'server' => $this->requestState->server,
                    '_group' => hash('xxh128', implode('|', $record->routeMethods).",{$record->routeDomain},{$record->routePath}"),
                    'trace_id' => $this->requestState->trace,
                    'user' => $this->requestState->user->id(),
                    // --- //
                    'method' => $record->method,
                    'url' => $record->url,
                    'route_name' => $record->routeName,
                    'route_methods' => $record->routeMethods,
                    'route_domain' => $record->routeDomain,
                    'route_path' => $record->routePath,
                    'route_action' => $record->routeAction,
                    'ip' => $record->ip,
                    'duration' => $record->duration,
                    'status_code' => $record->statusCode,
                    'request_size' => $record->requestSize,
                    'response_size' => $record->responseSize,
                    // --- //
                    'bootstrap' => $this->requestState->stageDurations[ExecutionStage::Bootstrap->value],
                    'before_middleware' => $this->requestState->stageDurations[ExecutionStage::BeforeMiddleware->value],
                    'action' => $this->requestState->stageDurations[ExecutionStage::Action->value],
                    'render' => $this->requestState->stageDurations[ExecutionStage::Render->value],
                    'after_middleware' => $this->requestState->stageDurations[ExecutionStage::AfterMiddleware->value],
                    'sending' => $this->requestState->stageDurations[ExecutionStage::Sending->value],
                    'terminating' => $this->requestState->stageDurations[ExecutionStage::Terminating->value],
                    'exceptions' => $this->requestState->exceptions,
                    'logs' => $this->requestState->logs,
                    'queries' => $this->requestState->queries,
                    'lazy_loads' => $this->requestState->lazyLoads,
                    'jobs_queued' => $this->requestState->jobsQueued,
                    'mail' => $this->requestState->mail,
                    'notifications' => $this->requestState->notifications,
                    'outgoing_requests' => $this->requestState->outgoingRequests,
                    'files_read' => $this->requestState->filesRead,
                    'files_written' => $this->requestState->filesWritten,
                    'cache_events' => $this->requestState->cacheEvents,
                    'hydrated_models' => $this->requestState->hydratedModels,
                    'peak_memory_usage' => $this->requestState->peakMemory(),
                    'exception_preview' => Str::tinyText($this->requestState->exceptionPreview),
                    'context' => $this->serializedContext(),
                ];
            },
        ];
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
