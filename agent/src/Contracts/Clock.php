<?php

namespace Laravel\NightwatchAgent\Contracts;

interface Clock
{
    public function time(): int;
}
