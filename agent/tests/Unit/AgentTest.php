<?php

use Symfony\Component\Process\Process;

it('can start the agent', function (string $via) {
    $port = rand(9000, 9999);
    $env = [
        'NW_LISTEN_ON' => "127.0.0.1:{$port}",
        'NW_VIA_PHAR' => match ($via) {
            'source' => '0',
            'phar' => '1',
        },
    ];
    $process = Process::fromShellCommandline('php '.__DIR__.'/agent-wrapper.php');
    $process->setTimeout(10);

    $output = [];
    $process->run(function ($type, $o) use ($process, &$output) {
        $output[] = trim($o);

        if (count($output) === 2) {
            $process->stop(3);
        }
    }, $env);

    expect($output)->toHaveCount(2, 'Expected 2 items in output. Received: '.json_encode($output));
    expect($output[0])->toContain('[INFO] Nightwatch agent initiated');
    expect($output[1])->toContain('[INFO] Authentication successful');
})->with(['source', 'phar']);
