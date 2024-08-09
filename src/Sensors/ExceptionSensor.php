<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\View\ViewException;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\UserProvider;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

/**
 * @internal
 */
final class ExceptionSensor
{
    public function __construct(
        private Clock $clock,
        private ExecutionParent $executionParent,
        private Location $location,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id
     */
    public function __invoke(Throwable $e): void
    {
        $nowMicrotime = $this->clock->microtime();
        [$file, $line] = $this->location->forException($e);
        $normalizedException = match ($e->getPrevious()) {
            null => $e,
            default => match (true) {
                $e instanceof ViewException,
                $e instanceof IgnitionViewException => $e->getPrevious() ?? $e,
                default => $e,
            },
        };

        $this->executionParent->exceptions++;

        $this->recordsBuffer->writeException(new Exception(
            timestamp: $nowMicrotime,
            deploy: $this->executionParent->deploy,
            server: $this->executionParent->server,
            group: hash('md5', implode(',', [$normalizedException::class, $normalizedException->getCode(), $file, $line])),
            trace_id: $this->executionParent->traceId,
            execution_context: $this->executionParent->executionContext,
            execution_id: $this->executionParent->executionId,
            execution_stage: $this->executionParent->executionStage,
            user: $this->user->id(),
            class: $normalizedException::class,
            file: $file,
            line: $line ?? 0,
            message: $normalizedException->getMessage(),
            code: $normalizedException->getCode(),
            trace: $normalizedException->getTraceAsString(),
        ));
    }
}
