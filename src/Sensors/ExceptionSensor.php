<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\Exception;
use Throwable;

final class ExceptionSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, file, line
     * TODO allow auth to be customised? Inject auth manager into the class.
     */
    public function __invoke(Throwable $e): void
    {
        $now = CarbonImmutable::now('UTC');

        $this->recordsBuffer->writeException(new Exception(
            timestamp: $now->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            user: (string) Auth::id(),
            class: $e::class,
            file: 'app/Models/User.php',
            line: 5,
            message: $e->getMessage(),
            code: $e->getCode(),
            trace: $e->getTraceAsString(),
        ));
    }
}
