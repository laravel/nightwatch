<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\Exception;
use Laravel\Nightwatch\UserProvider;
use Throwable;

final class ExceptionSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
        private Clock $clock,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, file, line
     */
    public function __invoke(Throwable $e): void
    {
        $nowMicrotime = $this->clock->microtime();

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
            class: $e::class,
            file: 'app/Models/User.php',
            line: 5,
            message: $e->getMessage(),
            code: $e->getCode(),
            trace: $e->getTraceAsString(),
        ));
    }
}
