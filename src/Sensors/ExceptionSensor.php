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

    public function __invoke(Throwable $e): void
    {
        $now = CarbonImmutable::now('UTC');

        $this->recordsBuffer->writeException(new Exception(
            timestamp: $now->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''), // TODO
            trace_id: $this->traceId,
            execution_context: 'request', // TODO
            execution_id: '00000000-0000-0000-0000-000000000000', // TODO
            user: Auth::id() ?? '', // TODO allow this to be customised
            class: $e::class,
            file: 'app/Models/User.php', //TODO
            line: 5, // TODO
            message: $e->getMessage(),
            code: $e->getCode(),
            trace: $e->getTraceAsString(),
        ));
    }
}
