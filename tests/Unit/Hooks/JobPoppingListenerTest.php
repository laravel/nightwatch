<?php

use Illuminate\Queue\Events\JobPopping;
use Laravel\Nightwatch\Hooks\JobPoppingListener;
use Laravel\Nightwatch\RecordsBuffer;

it('gracefully handles exceptions', function () {
    nightwatch()->state->records = $buffer = new class extends RecordsBuffer
    {
        public bool $thrownInFlush = false;

        public function flush(): string
        {
            $this->thrownInFlush = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $event = new JobPopping('redis');

    $listener = new JobPoppingListener(nightwatch());
    $listener($event);

    expect($buffer->thrownInFlush)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
});
