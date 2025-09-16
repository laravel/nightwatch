<?php

namespace Tests\Feature\Console;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

use function str_contains;

class AgentCommandTest extends TestCase
{
    public function test_it_can_run_the_agent_command(): void
    {
        $output = '';

        try {
            Process::timeout(10)->run('vendor/bin/testbench nightwatch:agent', function ($type, $o) use (&$output, $process) {
                $output .= $o;

                if (str_contains($o, 'Authentication successful')) {
                    $process->signal(SIGTERM);
                }
            });
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException('Failed to authenticate or stop the agent running. Output:'.PHP_EOL.$output, previous: $e);
        }

        $this->assertStringContainsString('Authentication successful', $output);
    }
}
