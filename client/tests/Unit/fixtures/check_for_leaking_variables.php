<?php

// Wrapped in a closure so that changes made to the global scope do not impact the locally defined vars.
call_user_func(static function () {
    echo count(get_defined_vars());
    echo PHP_EOL;

    require __DIR__.'./../../../entry.php';

    echo count(get_defined_vars());
});
