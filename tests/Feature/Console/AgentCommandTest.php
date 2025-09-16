<?php

namespace Tests\Feature\Console;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

use function sleep;
use function str_contains;

class AgentCommandTest extends TestCase
{
    public function test_it_can_run_the_agent_command(): void
    {
        $output = '';
        $process = Process::timeout(10)->start('vendor/bin/testbench nightwatch:agent');

        try {
            $result = $process->wait(function ($type, $o) use (&$output, $process) {
                $output .= $o;

                if (! str_contains($o, 'Authentication successful')) {
                    return;
                }

                $process->signal(SIGTERM);

                $tries = 0;

                while ($tries < 3) {
                    if (! $process->running()) {
                        return;
                    }

                    $tries++;
                    sleep(1);
                }

                $process->signal(SIGKILL);
            });
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException('Failed to authenticate or stop the agent running. Output:'.PHP_EOL.$output, previous: $e);
        }

        $this->assertStringContainsString('Authentication successful', $output);
    }
}
