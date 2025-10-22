<?php

namespace Nightwatch;

use RuntimeException;
use Throwable;

use function array_keys;
use function array_reduce;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function gettype;
use function intval;
use function is_callable;
use function stream_get_meta_data;
use function stream_set_timeout;
use function strlen;
use function substr;

/**
 * @param  resource  $stream
 * @param  (callable(): string)|string  $payload
 */
function fwrite_all($stream, callable|string $payload): void
{
    $payload = is_callable($payload) ? $payload() : $payload;
    $originalPayloadLength = strlen($payload);
    $written = 0;

    while (true) {
        $thisWrite = fwrite($stream, $payload);

        if ($thisWrite === false) {
            throw runtime_exception_with_steam_meta('Unable to write to stream', $stream);
        }

        $written += $thisWrite;

        if ($written >= $originalPayloadLength) {
            return;
        }

        $payload = substr($payload, $thisWrite);
    }
}

/**
 * @param  resource  $stream
 * @param  int<1, max>  $length
 */
function fread_all($stream, int $length): string
{
    $content = '';
    $attempts = 0;

    do {
        $thisRead = fread($stream, $length);

        if ($thisRead === false) {
            throw runtime_exception_with_steam_meta('Unable to read from stream', $stream);
        }

        $content .= $thisRead;
        $attempts++;
    } while (strlen($content) < $length && ! feof($stream) && $attempts < 5);

    return $content;
}

/**
 * @param  resource  $stream
 */
function stream_get_formatted_meta_data($stream): string
{
    if (stream_closed($stream)) {
        return 'closed: true';
    }

    $meta = stream_get_meta_data($stream);

    return array_reduce(array_keys($meta), static function ($carry, $key) use ($meta) {
        try {
            return $carry.$key.': '.match ($meta[$key]) {
                true => 'true',
                false => 'false',
                default => $meta[$key],
            }.PHP_EOL;
        } catch (Throwable) { // @phpstan-ignore catch.neverThrown
            return $carry;
        }
    }, '');
}

/**
 * @param  resource  $stream
 */
function fclose_safely($stream): void
{
    try {
        if (! stream_closed($stream)) {
            fclose($stream);
        }
    } catch (Throwable) {
        //
    }
}

/**
 * @param  resource  $stream
 */
function stream_closed($stream): bool
{
    return gettype($stream) === 'resource (closed)';
}

/**
 * @param  resource  $stream
 */
function runtime_exception_with_steam_meta(string $message, $stream): RuntimeException
{
    return new RuntimeException($message.PHP_EOL.'---'.PHP_EOL.stream_get_formatted_meta_data($stream));
}

/**
 * @param  resource  $stream
 */
function stream_configure_read_timeout($stream, float $timeout): void
{
    $timeout = [
        'seconds' => $seconds = (int) $timeout,
        'microseconds' => intval(($timeout - $seconds) * 1_000_000),
    ];

    $timeoutConfigured = stream_set_timeout(
        $stream,
        $timeout['seconds'],
        $timeout['microseconds'],
    );

    if ($timeoutConfigured === false) {
        throw runtime_exception_with_steam_meta('Failed configuring agent read timeout', $stream);
    }
}
