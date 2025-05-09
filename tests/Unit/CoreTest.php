<?php

use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Payload;

it('gracefully handles exceptions thrown while ingesting', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    nightwatch()->ingest = new class extends FakeIngest
    {
        public bool $thrownInWrite = false;

        public function write(Payload $payload): void
        {
            $this->thrownInWrite = true;

            throw new RuntimeException('Whoops!');
        }

        public function ping(): void
        {
            //
        }
    };

    nightwatch()->digest();

    expect(nightwatch()->ingest->thrownInWrite)->toBeTrue();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Whoops!');
});
