<?php

namespace Tests\Feature\Console;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

use function str_contains;

class AgentCommandTest extends TestCase
{
    public function test_it_can_run_the_agent_command(): void
    {
        $output = '';
        $process = Process::timeout(5)->start('vendor/bin/testbench nightwatch:agent');
        try {
            $result = $process->wait(function ($type, $o) use (&$output, $process) {
                $output .= $o;

                if ($type === 'out' && str_contains($o, 'Authentication successful')) {
                    $process->signal(SIGKILL);
                }
            });
        } catch (ProcessTimedOutException $e) {
            echo $output;

            throw $e;
        }

        $this->assertStringContainsString('Authentication successful', $output);
    }
}
