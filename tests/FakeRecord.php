<?php

namespace Tests;

class FakeRecord
{
    public static function make(): array
    {
        return [
            't' => 'fake-record',
        ];
    }
}
