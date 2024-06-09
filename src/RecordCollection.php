<?php

namespace Laravel\Nightwatch;

use Illuminate\Support\Collection;

final class RecordCollection extends Collection
{
    public function __construct()
    {
        parent::__construct([]);

        $this->flush();
    }

    public function flush(): void
    {
        $this->items = [
            'execution_parent' => new Collection([
                'queries' => 0,
                'queries_duration' => 0,
                'lazy_loads' => 0,
                'lazy_loads_duration' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'mail_duration' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'notifications_duration' => 0,
                'outgoing_requests' => 0,
                'outgoing_requests_duration' => 0,
                'files_read' => 0,
                'files_read_duration' => 0,
                'files_written' => 0,
                'files_written_duration' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
                'hydrated_models' => 0,
            ]),
            'requests' => new Collection(),
            'cache_events' => new Collection(),
            'commands' => new Collection(),
            'exceptions' => new Collection(),
            'job_attempts' => new Collection(),
            'lazy_loads' => new Collection(),
            'logs' => new Collection(),
            'mail' => new Collection(),
            'notifications' => new Collection(),
            'outgoing_requests' => new Collection(),
            'queries' => new Collection(),
            'queued_jobs' => new Collection(),
        ];
    }
}
