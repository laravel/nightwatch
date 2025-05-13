<?php

use Illuminate\Http\Client\Factory;
use Laravel\Nightwatch\Hooks\HttpClientFactoryResolvedHandler;

it('gracefully handles exceptions', function () {
    $factory = new class extends Factory
    {
        public bool $thrownInGlobalMiddleware = false;

        public function globalMiddleware($middleware)
        {
            $this->thrownInGlobalMiddleware = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $handler = new HttpClientFactoryResolvedHandler(nightwatch());
    $handler($factory);

    expect($factory->thrownInGlobalMiddleware)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
