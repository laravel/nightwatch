<?php

use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\LazyValue;
use Laravel\Nightwatch\Records\Mail;
use Laravel\Nightwatch\RecordsBuffer;

it('only keeps 500 records in memory', function () {
    $buffer = new RecordsBuffer;

    for ($i = 0; $i < 1_000; $i++) {
        $buffer->write(new Mail(
            timestamp: $i / 1000,
            deploy: '',
            server: '',
            _group: '',
            trace_id: '',
            execution_source: '',
            execution_id: new LazyValue(fn () => ''),
            execution_stage: ExecutionStage::Action,
            user: '',
            mailer: '',
            class: '',
            subject: '',
            to: 0,
            cc: 0,
            bcc: 0,
            attachments: 0,
            duration: 0,
            failed: false,
        ));
    }

    $output = $buffer->flush();
    expect($output)->not->toContain('"timestamp":0.499,');
    expect($output)->toContain('"timestamp":0.5,');
    expect(preg_match_all('/\"t\"\:\"mail\"/', $output))->toBe(500);
});
