<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

use function collect;
use function rescue;
use function str_contains;
use function with;

class AgentCommandTest extends TestCase
{
    public function test_it_can_run_the_agent_command(): void
    {
        $output = collect();
        $result = with(Process::timeout(5)->start('vendor/bin/testbench nightwatch:agent'), function ($process) use ($output) {
            return rescue(fn () => $process->wait(function ($type, $o) use ($output) {
                $output[] = $o;

                if ($type === 'out' && str_contains($o, 'Authentication successful')) {
                    // $process->signal(SIGINT);
                }
            }), report: false);
        });

        $this->assertNotNull($result, $output->implode(PHP_EOL));
    }
}
