<?php

$listenOn = getenv('NIGHTWATCH_INGEST_URI') ?: '0.0.0.0:2407';
$refreshToken = getenv('NIGHTWATCH_TOKEN');
require 'agent/build/agent.phar';
