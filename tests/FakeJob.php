<?php

namespace Tests;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Str;

use function once;

class FakeJob extends Job implements JobContract
{
    use Dispatchable;

    public function handle(): void {}

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return once(fn () => (string) Str::uuid());
    }

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return '{"job":""}';
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return 1;
    }
}
