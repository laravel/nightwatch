<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\View\ViewException;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\UserProvider;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

final class ExceptionSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
        private Clock $clock,
        private Location $location,
        private string $deployId,
        private string $server,
        private string $traceId,
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

        $previous = $e->getPrevious();

        $this->recordsBuffer->writeException(new Exception(
            timestamp: (int) $nowMicrotime,
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($nowMicrotime),
            user: $this->user->id(),
            class: match (true) {
                $e instanceof ViewException && $previous => $previous,
                $e instanceof IgnitionViewException && $previous => $previous::class, // @phpstan-ignore class.notFound
                default => $e::class,
            },
            file: $file,
            line: $line ?? 0,
            message: $e->getMessage(),
            code: $e->getCode(),
            trace: $e->getTraceAsString(),
        ));
    }
}
