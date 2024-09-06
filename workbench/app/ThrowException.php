<?php

namespace App;

use RuntimeException;

final class ThrowException
{
    public function throw()
    {
        throw new RuntimeException('Whoops!');
    }
}
