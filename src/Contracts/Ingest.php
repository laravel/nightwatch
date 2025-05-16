<?php

namespace Laravel\Nightwatch\Contracts;

use Laravel\Nightwatch\Records\Record;

/**
 * @internal
 */
interface Ingest
{
    public function write(Record $record): void;

    public function ping(): void; // todo remove

    public function filter(callable $filter): void;

    public function digest(): void;

    public function flush(): void;
}
