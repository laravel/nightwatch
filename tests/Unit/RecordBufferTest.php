<?php

use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Records\Mail;

it('only keeps 100 records in memory', function () {
    $buffer = new RecordsBuffer;

    for ($i = 0; $i < 1_000; $i++) {
        $buffer->write(new Mail(
            timestamp: $i / 1000,
            deploy: '',
            server: '',
            _group: '',
            trace_id: '',
            execution_source: '',
            execution_id: '',
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
