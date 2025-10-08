<?php

namespace Tests\Feature\Console;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

use function sleep;

class DeployCommandTest extends TestCase
{
    public function test_it_can_run_the_agent_command(): void
    {
        $output = '';
        $process = Process::timeout(10)->start('NIGHTWATCH_TOKEN="test-token" \
          NIGHTWATCH_DEPLOY="v1.2.3" \
          vendor/bin/testbench nightwatch:deploy'
        );

        try {
            $result = $process->wait(function ($type, $o) use (&$output, $process) {
                $output .= $o;

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
            throw new RuntimeException('Failed to deploy or stop the agent running. Output:'.PHP_EOL.$output, previous: $e);
        }

        $this->assertStringContainsString('Deployment successful', $output);
    }
}
