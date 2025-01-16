<?php

use Laravel\Nightwatch\Contracts\LocalIngest;

it('gracefully handles exceptions thrown while ingesting', function () {
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
