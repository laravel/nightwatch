<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Concerns\RecordsContext;
use Laravel\Nightwatch\Concerns\RedactsHeaders;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Request as RequestRecord;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function array_map;
use function array_sum;
use function assert;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_encode;
use function rescue;
use function sort;
use function strlen;
use function tap;

/**
 * @internal
 */
final class RequestSensor
{
    use RecordsContext;
    use RedactsHeaders;

    /**
     * @param  list<string>  $redactPayloadFields
     * @param  list<string>  $redactHeaders
     */
    public function __construct(
        private RequestState $requestState,
        private bool $capturePayload,
        private array $redactPayloadFields,
        private array $redactHeaders,
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
                headers: tap(clone $request->headers, static function ($headers) {
                    $headers->remove('php-auth-user');
                    $headers->remove('php-auth-pw');
                    $headers->remove('php-auth-digest');
                }),
                payload: clone $request->request,
                files: clone $request->files,
            ),
            function () use ($request, $response, $record) {
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
                    'headers' => rescue(
                        fn () => Str::text(json_encode((object) $this->redactHeaders($record->headers, $this->redactHeaders)->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
                        '{"_nightwatch_error":"Failed to serialize headers"}',
                        static function ($e) {
                            Nightwatch::unrecoverableExceptionOccurred($e);

                            return false;
                        },
                    ),
                    'payload' => $this->serializePayload($request, $response, $record),
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

    private function serializePayload(Request $request, Response $response, RequestRecord $record): string
    {
        if ($response->getStatusCode() !== 500) {
            return '';
        }

        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true) && $record->payload->count() === 0 && $record->files->count() === 0) {
            return '';
        }

        if (! $this->capturePayload) {
            return '{"_nightwatch_error":"NOT_ENABLED"}';
        }

        if (! $this->isSupportedContentType($request) && $record->payload->count() === 0 && $record->files->count() === 0) {
            return '{"_nightwatch_error":"UNSUPPORTED_CONTENT_TYPE"}';
        }

        return Str::text(rescue(
            fn () => json_encode([
                ...$this->redactRecursively($record->payload->all()),
                '_nightwatch_files' => $this->mapUploadedFilesRecursively($record->files->all()),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            '{"_nightwatch_error":"SERIALIZATION_FAILED"}',
            static function ($e) {
                Nightwatch::unrecoverableExceptionOccurred($e);

                return false;
            }
        ));
    }

    private function isSupportedContentType(Request $request): bool
    {
        return $request->isJson()
            || in_array($request->headers->get('content-type'), ['application/x-www-form-urlencoded', 'multipart/form-data'], true);
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    private function redactRecursively(array $array): array
    {
        return Arr::map($array, function ($value, $key) {
            if (is_array($value)) {
                return $this->redactRecursively($value);
            }

            return ! in_array($key, $this->redactPayloadFields, true) || ! is_string($value) ? $value : '['.strlen($value).' bytes redacted]';
        });
    }

    /**
     * @param  array<mixed>  $files
     * @return array<mixed>
     */
    private function mapUploadedFilesRecursively(array $files): array
    {
        return array_map(function ($file) {
            if (is_array($file)) {
                return $this->mapUploadedFilesRecursively($file);
            }

            assert($file instanceof UploadedFile);

            return [
                'originalName' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'error' => $file->getError(),
            ];
        }, $files);
    }
}
