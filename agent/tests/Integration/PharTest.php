<?php

it('can start the agent and authenticate', function () {
    [$output, $e] = run(via: 'phar', timeout: 10, until: fn ($output) => str_contains($output, 'Authentication'));

    expect($e)->toBeNull($e?->getMessage() ?? '');
    expect($output)->toMatchLog(<<<'OUTPUT'
        {date} {info} Authentication successful {duration}
        OUTPUT);
});
