<?php

use Illuminate\Http\Client\Factory;
use Laravel\Nightwatch\Hooks\HttpClientFactoryResolvedHandler;

it('gracefully handles exceptions', function () {
    $factory = new class extends Factory
    {
        public bool $thrown = false;

        public function globalMiddleware($middleware)
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $handler = new HttpClientFactoryResolvedHandler(nightwatch());
    $handler($factory);

    expect($factory->thrown)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
