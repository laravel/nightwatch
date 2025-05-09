<?php

use Laravel\Nightwatch\Facades\Nightwatch;
use Tests\FakeIngest;

it('gracefully handles exceptions thrown while ingesting', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    fakeIngest(new class extends FakeIngest
    {
        public bool $thrownInDigest = false;

        public function digest(): void
        {
            $this->thrownInDigest = true;

            throw new RuntimeException('Whoops!');
        }
    });

    nightwatch()->digest();

    expect(nightwatch()->ingest->thrownInDigest)->toBeTrue();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Whoops!');
});
