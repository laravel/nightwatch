<?php

namespace Laravel\Nightwatch\Contracts;

use Laravel\Nightwatch\Records\Record;

/**
 * @internal
 */
interface Ingest
{
    public function write(Record $record): void;

    public function ping(): void;

    public function shouldDigest(bool $bool): void;

    public function digest(): void;

    public function flush(): void;
}
