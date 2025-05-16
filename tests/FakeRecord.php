<?php

namespace Tests;

use Laravel\Nightwatch\Records\Record;

class FakeRecord extends Record
{
    public function __construct(
        public string $t = 'fake-record',
    ) {
        //
    }
}
