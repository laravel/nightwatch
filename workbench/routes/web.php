<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return json_encode(passthru('gi rev-parse HEAD'));
});
