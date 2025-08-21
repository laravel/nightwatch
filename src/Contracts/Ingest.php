<?php

namespace Laravel\Nightwatch\Contracts;

/**
 * @internal
 */
interface Ingest
{
    /**
     * @param  array<mixed>  $record
     */
    public function write(array $record): void;

    /**
     * @param  array<mixed>  $record
     */
    public function writeNow(array $record): void;

    public function ping(): void;

    public function shouldDigest(bool $bool): void;

    public function digest(): void;

    public function flush(): void;
}
