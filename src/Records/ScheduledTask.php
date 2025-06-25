<?php

namespace Laravel\Nightwatch\Records;

final class ScheduledTask
{
    /**
     * @param  'processed'|'skipped'|'failed'  $status
     */
    public function __construct(
        public readonly string $name,
        public readonly string $cron,
        public readonly string $timezone,
        public readonly bool $withoutOverlapping,
        public readonly bool $onOneServer,
        public readonly bool $runInBackground,
        public readonly bool $evenInMaintenanceMode,
        public readonly string $status,
        public readonly int $duration,
    ) {
        //
    }
}
