<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\RecordCollection;
use Throwable;

final class ExceptionsSensor
{
    public function __construct(
        private RecordCollection $records,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(Throwable $e): void
    {
        $now = CarbonImmutable::now();

        $this->records['exceptions'][] = [
            // TODO Can I do this without Carbon?
            // TODO `time` is a float. Does this correctly adjust?
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'deploy_id' => $this->deployId,
            'server' => $this->server,
            'group' => hash('sha256', ''), // TODO
            'trace_id' => $this->traceId,
            'execution_context' => 'request', // TODO
            'execution_id' => '00000000-0000-0000-0000-000000000000', // TODO
            'user' => Auth::id() ?? '', // TODO allow this to be customised
            'class' => $e::class,
            'file' => 'app/Models/User.php', //TODO
            'line' => 5,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'trace' => '', // TODO
        ];

        $executionParent = $this->records['execution_parent'];

        // TODO we should track the number of exceptions.
    }
}
