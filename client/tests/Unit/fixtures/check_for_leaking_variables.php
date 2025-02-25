<?php

// Wrapped in a closure to simulate a local scoped block so global variables are not
// counted
call_user_func(static function () {
    echo count(get_defined_vars());
    echo PHP_EOL;

    require __DIR__.'./../../../entry.php';

    echo count(get_defined_vars());
});
