<?php

namespace Tests\Integration;

use Symfony\Component\Process\Process;
use Tests\TestCase;

use function str_contains;

class HealthCheckTest extends TestCase
{
    public function test_it_errors_when_agent_is_not_running(): void
    {
        $process = Process::fromShellCommandline('php '.__DIR__.'/../../nightwatch-status')
            ->setTimeout(2);

        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertSame('', $process->getOutput());
        $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[ERROR\] Failed connecting to the agent: Connection refused \[\d+\]\n$/", $process->getErrorOutput());
    }

    public function test_it_can_check_status(): void
    {
        $process = Process::fromShellCommandline('php '.__DIR__.'/../../nightwatch-status')
            ->setTimeout(2);
        [$output, $e] = $this->runAgent(via: 'phar', timeout: 10, until: function ($output) use ($process, &$listenOn) {
            if (str_contains($output, 'Authentication')) {
                $process->run(env: ['NIGHTWATCH_INGEST_URI' => $listenOn]);

                return true;
            }

            return false;
        }, listenOn: $listenOn);

        $this->assertSame(0, $process->getExitCode());
        $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[INFO\] The Nightwatch agent is running and accepting connections$/", $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT, $output);
    }
}
