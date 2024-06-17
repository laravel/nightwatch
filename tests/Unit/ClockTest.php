<?php

it('always ends execution offset with two trailing commas', function () {
    // Because `microtime(true)` rounds to four decimal point precision, we
    // don't want weird float math to give us microsecond precision that is not
    // real.
})->todo();
