<?php

namespace Laravel\Nightwatch\Contracts;

interface Ingest
{
    public function write(string $payload): void;
}
