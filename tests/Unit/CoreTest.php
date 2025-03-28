<?php

use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Facades\Nightwatch;

it('gracefully handles exceptions thrown while ingesting', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    nightwatch()->ingest = new class implements LocalIngest
    {
        public bool $thrown = false;

        public function write(string $payload): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    nightwatch()->ingest();

    expect(nightwatch()->ingest->thrown)->toBeTrue();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Whoops!');
});
