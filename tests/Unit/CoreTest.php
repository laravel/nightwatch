<?php

use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Facades\Nightwatch;

it('gracefully handles exceptions thrown while ingesting', function () {
    Nightwatch::handleUnrecoverableExceptionsUsing(fn () => null);
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
});
