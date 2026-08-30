<?php

namespace Tests\Integration;

use Tests\TestCase;

use function str_contains;

class PharTest extends TestCase
{
    public function test_it_can_start_the_agent_and_authenticate(): void
    {
        [$output, $e] = $this->runAgent(via: 'phar', timeout: 10, until: fn ($output) => str_contains($output, 'Authentication'));

        $this->assertNull($e, $e?->getMessage() ?? '');
        $this->assertLogMatches(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        {date} {info} Graceful down initiated
        {date} {info} Shutting down
        OUTPUT, $output);
    }

    public function test_it_shuts_down_cleanly_on_sigint(): void
    {
        [$output, , $exitCode] = $this->runAgent(
            via: 'phar',
            timeout: 10,
            until: fn ($output) => str_contains($output, 'Authentication'),
            stopSignal: SIGINT,
        );

        $this->assertStringContainsString('Shutting down', $output, "Agent did not log a clean shutdown after SIGINT.\n=== OUTPUT ===\n{$output}");
        $this->assertSame(0, $exitCode, "Agent did not exit cleanly after SIGINT (exit code {$exitCode}).\n=== OUTPUT ===\n{$output}");
    }

    public function test_it_shuts_down_cleanly_on_sigterm(): void
    {
        [$output, , $exitCode] = $this->runAgent(
            via: 'phar',
            timeout: 10,
            until: fn ($output) => str_contains($output, 'Authentication'),
            stopSignal: SIGTERM,
        );

        $this->assertStringContainsString('Shutting down', $output, "Agent did not log a clean shutdown after SIGTERM.\n=== OUTPUT ===\n{$output}");
        $this->assertSame(0, $exitCode, "Agent did not exit cleanly after SIGTERM (exit code {$exitCode}).\n=== OUTPUT ===\n{$output}");
    }

    public function test_it_shuts_down_cleanly_on_sigquit(): void
    {
        [$output, , $exitCode] = $this->runAgent(
            via: 'phar',
            timeout: 10,
            until: fn ($output) => str_contains($output, 'Authentication'),
            stopSignal: SIGQUIT,
        );

        $this->assertStringContainsString('Shutting down', $output, "Agent did not log a clean shutdown after SIGQUIT.\n=== OUTPUT ===\n{$output}");
        $this->assertSame(0, $exitCode, "Agent did not exit cleanly after SIGQUIT (exit code {$exitCode}).\n=== OUTPUT ===\n{$output}");
    }
}
