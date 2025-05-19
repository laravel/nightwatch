<?php

namespace Tests\Unit\Hooks;

use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Foundation\Application;
use Laravel\Nightwatch\Hooks\ArtisanStartingListener;
use Tests\TestCase;

use function version_compare;

class ArtisanStartingHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $this->markTestSkippedWhen(version_compare(Application::VERSION, '12.0.0', '<'), <<<'MESSAGE'
            This test only fails when there are type declations which where introduced in 12.x
            MESSAGE);
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
