<?php

namespace Tests\Unit\Hooks;

use Illuminate\Console\Events\ArtisanStarting;
use Laravel\Nightwatch\Hooks\ArtisanStartingListener;
use Tests\TestCase;

class ArtisanStartingHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $event = new class extends ArtisanStarting
        {
            public function __construct()
            {
                //
            }
        };

        $listener = new ArtisanStartingListener($this->core);
        $listener($event);

        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
