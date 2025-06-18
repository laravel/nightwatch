<?php

namespace Tests\Fakes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DummyJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle() {}
}
