<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Nightwatch\Facades\Nightwatch;

class SampledJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private float $rate)
    {
        //
    }

    public function handle(): void
    {
        Nightwatch::sample($this->rate);
    }
}
