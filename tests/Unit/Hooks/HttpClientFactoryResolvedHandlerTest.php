<?php

namespace Tests\Unit\Hooks;

use Illuminate\Http\Client\Factory;
use Laravel\Nightwatch\Hooks\HttpClientFactoryResolvedHandler;
use RuntimeException;
use Tests\TestCase;

class HttpClientFactoryResolvedHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $factory = new class extends Factory
        {
            public bool $thrownInGlobalMiddleware = false;

            public function globalMiddleware($middleware): void
            {
                $this->thrownInGlobalMiddleware = true;

                throw new RuntimeException('Whoops!');
            }
        };

        $handler = new HttpClientFactoryResolvedHandler($this->core);
        $handler($factory);

        $this->assertTrue($factory->thrownInGlobalMiddleware);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
