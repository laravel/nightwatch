<?php

use Symfony\Component\Process\Process;

test('entry point can be accessed and ingest resolved multiple times', function () {
    $path = __DIR__.'/fixtures/include_entry_point_multiple_times.php';
    $process = Process::fromShellCommandline("php -f {$path}");
    $process->setTimeout(10);

    $process->mustRun();

    expect($process->getOutput())->toBe(<<<OUTPUT
        Closure
        NightwatchClient_kden27khxA4QoEfj\\Laravel\\NightwatchClient\Ingest
        Closure
        NightwatchClient_kden27khxA4QoEfj\\Laravel\\NightwatchClient\Ingest
        Closure
        NightwatchClient_kden27khxA4QoEfj\\Laravel\\NightwatchClient\Ingest
        OUTPUT);
});

test('variables do not leak into scope', function () {
    $path = __DIR__.'/fixtures/check_for_leaking_variables.php';
    $process = Process::fromShellCommandline("php -f {$path}");
    $process->setTimeout(10);

    $process->mustRun();

    expect($process->getOutput())->toBe(<<<'OUTPUT'
        0
        0
        OUTPUT);
});
