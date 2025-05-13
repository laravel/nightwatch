<?php

namespace Tests;

use Laravel\Nightwatch\Records\Record;

class FakeRecord extends Record
{
    public string $t = 'fake-record';
}
