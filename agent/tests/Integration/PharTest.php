<?php

namespace Tests\Integration;

use Tests\TestCase;

use function expect;
use function run;
use function str_contains;

class PharTest extends TestCase
{
    public function test_it_can_start_the_agent_and_authenticate(): void
    {
        [$output, $e] = run(via: 'phar', timeout: 10, until: fn ($output) => str_contains($output, 'Authentication'));

        expect($e)->toBeNull($e?->getMessage() ?? '');
        expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
    }
}
