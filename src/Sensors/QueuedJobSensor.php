<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\UserProvider;

final class QueuedJobSensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private UserProvider $user,
        private Clock $clock,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id
     */
    public function __invoke(JobQueued $event)
    {
        $nowMicrotime = $this->clock->microtime();

        $this->recordsBuffer->writeQueuedJob(new QueuedJob(
            timestamp: (int) $nowMicrotime,
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($nowMicrotime),
            user: $this->user->id(),
            job_id: $event->payload()['uuid'],
            name: match (true) {
                is_string($event->job) => $event->job,
                method_exists($event->job, 'displayName') => $event->job->displayName(),
                default => $event->job::class,
            },
            connection: $event->connectionName,
            queue: 'default',
        ));

        $this->executionParent->jobs_queued++;
    }
}
