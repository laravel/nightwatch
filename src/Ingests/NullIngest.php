<?php

namespace Laravel\Nightwatch\Ingests;

use Laravel\Nightwatch\IngestSucceededResult;
use React\Promise\Promise;

final class NullIngest
{
    /**
     * @return Promise<IngestSucceededResult>
     */
    public function write(string $payload): Promise
    {
        $compressed = gzencode($payload);

        echo "Payload size: ".number_format(strlen($payload) / 1000 / 1000, 2)." MB";
        echo PHP_EOL;
        echo "Compressed size: ".number_format(strlen($compressed) / 1000 / 1000, 2)." MB";
        echo PHP_EOL;

        return new Promise(fn ($resolve) => $resolve(
            new IngestSucceededResult(0)
        ));
    }
}
