<?php

namespace Tests\Feature\Console;

use RuntimeException;
use Tests\FakeIngest;
use Tests\TestCase;

class StatusCommandTest extends TestCase
{
    public function test_it_fails_when_nightwatch_is_disabled()
    {
        $this->core->config['enabled'] = false;

        $this->artisan('nightwatch:status')
            ->expectsOutputToContain('Nightwatch is disabled')
            ->assertExitCode(1)
            ->run();
    }

    public function test_it_fails_when_ingest_throws_an_exception_while_pinging()
    {
        $this->fakeIngest(new class extends FakeIngest
        {
            public function ping(): void
            {
                throw new RuntimeException('Whoops!');
            }
        });

        $this->artisan('nightwatch:status')
            ->expectsOutputToContain('Whoops!')
            ->assertExitCode(1);
    }

    public function test_it_can_ping()
    {
        $this->fakeIngest();

        $this->artisan('nightwatch:status')
            ->expectsOutputToContain('The Nightwatch agent is running and accepting connections')
            ->assertExitCode(0);
    }
}
