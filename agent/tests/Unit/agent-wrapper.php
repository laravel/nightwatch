<?php

$listenOn = $_SERVER['NW_LISTEN_ON'];

if ($_SERVER['NW_VIA_PHAR']) {
    require __DIR__.'/../../build/agent.phar';
} else {
    require __DIR__.'/../../src/agent.php';
}
