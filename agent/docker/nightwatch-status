<?php

$ingestUri = getenv('NIGHTWATCH_INGEST_URI') ?: '127.0.0.1:2407';
$refreshToken = getenv('NIGHTWATCH_TOKEN') ?: '';
$tokenHash = substr(hash('xxh128', $refreshToken), 0, 7);

$timeout = 5.0;
$address = "tcp://{$ingestUri}";

$payloadVersion = 'v1';
$payload = 'PING';
$payloadToSend = null;

$payloadLength = strlen($payloadVersion) + 1 + strlen($tokenHash) + 1 + strlen($payload);
$payloadToSend = $payloadLength.':'.$payloadVersion.':'.$tokenHash.':'.$payload;

$stream = @stream_socket_client($address, $errno, $errstr, $timeout);
if (! $stream) {
    fwrite(STDERR, "Unable to connect to agent: {$errstr}\n");
    exit(1);
}

$written = fwrite($stream, $payloadToSend);
if ($written === false || $written < $payloadLength) {
    fwrite(STDERR, "Failed to write payload to agent\n");
    fclose($stream);
    exit(1);
}

$response = fread($stream, 4);
fclose($stream);

if ($response === '2:OK') {
    echo "Agent is healthy\n";
    exit(0);
} else {
    fwrite(STDERR, "Agent health check failed, response: [{$response}]\n");
    exit(1);
}
