<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str as StrType;
use Throwable;

/**
 * @internal
 */
final class JobProcessingListener
{
    /**
     * @param  Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(JobProcessing $event): void
    {
        try {
            $this->nightwatch->state->timestamp = $this->nightwatch->clock->microtime();
            $this->nightwatch->state->setId((string) Str::uuid());
            $this->nightwatch->state->executionPreview = StrType::tinyText($event->job->resolveName());
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
