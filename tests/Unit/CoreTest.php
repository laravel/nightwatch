<?php

namespace Tests\Unit;

use Laravel\Nightwatch\Facades\Nightwatch;
use RuntimeException;
use Tests\FakeIngest;
use Tests\TestCase;

class CoreTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions_thrown_while_ingesting(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $this->fakeIngest(new class extends FakeIngest
        {
            public bool $thrownInDigest = false;

            public function digest(): void
            {
                $this->thrownInDigest = true;

                throw new RuntimeException('Whoops!');
            }
        });

        $this->core->digest();

        $this->assertTrue($this->core->ingest->thrownInDigest);
        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());
    }
}
