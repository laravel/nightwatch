<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

use function str_contains;
use function with;

class AgentCommandTest extends TestCase
{
    public function test_it_can_run_the_agent_command(): void
    {
        $result = with(Process::timeout(5)->start('vendor/bin/testbench nightwatch:agent'), function ($process) {
            return $process->wait(function ($type, $output) use ($process) {
                if ($type === 'out' && str_contains($output, 'Authentication successful')) {
                    $process->signal(SIGINT);
                }
            });
        })->throw();

        // The above will throw an exception if it times out. So if we get here,
        // we know the agent command started as expected. Not need for any command
        // specific assertions here.
        $this->assertTrue(true);
    }
}
