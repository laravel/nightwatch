<?php

namespace Tests;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\FakeJob as JobsFakeJob;

class FakeJob extends JobsFakeJob implements JobContract
{
    //
}
