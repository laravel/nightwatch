<?php

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Tests\BrowserFake;
use Tests\LoopFake;
use Tests\Request;
use Tests\TcpServerFake;
use Tests\Timer;

if (! ($_SERVER['CI'] ?? false)) {
    try {
        Dotenv::createImmutable(__DIR__.'/../', '.env.testing')->load();
    } catch (InvalidPathException $e) {
        echo 'You have not configured your local `.env.testing` file. Please run `cp .env.example .env.testing` and configure the variables as needed.';

        exit(1);
    }
}

pest()->extends(Tests\TestCase::class);

expect()->extend('toMatchLog', function (string $log) {
    $log = "{date} {info} Nightwatch agent initiated: Listening on \[127.0.0.1:\d{4}\]\n{$log}";
    $log = str_replace('{date}', '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}', $log);
    $log = str_replace('{duration}', '\[\d(\.\d{1,3})?s\]', $log);
    $log = str_replace('{info}', '\[INFO\]', $log);
    $log = str_replace('{error}', '\[ERROR\]', $log);

    return $this->toMatch("#^{$log}$#");
});

expect()->extend('toHaveSent', function (array $requests) {
    foreach ($requests as $request) {
        if (($this->value->headers['content-encoding'] ?? null) === 'gzip') {
            $body = gzdecode($request->body);

            if ($body === false) {
                throw new RuntimeException('Unable to uncompress request payload.');
            }

            $request->body = gzdecode($request->body);
        }
    }

    $this->value = array_map(
        function ($request) {
            [$url, $headers, $body] = $request;

            if (($this->value->headers['content-encoding'] ?? null) === 'gzip') {
                $body = gzdecode($body);

                if ($body === false) {
                    throw new RuntimeException('Unable to uncompress request payload.');
                }
            }

            return new Request($url, $headers, $body);
        },
        $this->value->sentRequests,
    );

    return $this->toEqual($requests);
});

expect()->extend('toHaveSentNothing', fn () => $this->toHaveSent([]));

expect()->extend('toHaveRun', function (array $timers) {
    $this->value = array_map(fn ($timer) => new Timer(
        interval: $timer['interval'],
        runAt: $timer['runAt'],
        scheduledBy: $timer['scheduledBy'],
        scheduledAt: $timer['scheduledAt'],
    ), $this->value->timersRun);

    return $this->toEqual($timers);
});

expect()->extend('toHavePending', function (array $items) {
    $this->value = match ($this->value::class) {
        LoopFake::class => array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            runAt: $timer['runAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
        ), $this->value->pendingTimers),
        BrowserFake::class => $this->value->pendingResponses,
    };

    return $this->toEqual($items);
});

expect()->extend('toHaveCanceled', function (array $timers) {
    $this->value = match ($this->value::class) {
        LoopFake::class => array_map(fn ($timer) => new Timer(
            interval: $timer['interval'],
            canceledAt: $timer['canceledAt'],
            scheduledBy: $timer['scheduledBy'],
            scheduledAt: $timer['scheduledAt'],
        ), $this->value->canceledTimers),
    };

    return $this->toEqual($timers);
});

expect()->extend('toBeProcessing', function (array $responses) {
    $this->value = $this->value->processingResponses;

    return $this->toEqual($responses);
});

/**
 * @param  'source'|'phar'  $via
 * @param  (callable(string): bool)  $until
 * @return array{0: string, 1: Throwable|null}
 *
 * @param-out  BrowserFake  $ingestDetailsBrowser
 * @param-out  BrowserFake  $ingestBrowser
 * @param-out  LoopFake  $loop
 */
function run(string $via, ?callable $until = null, float $timeout = 0.5, ?BrowserFake &$ingestDetailsBrowser = null, ?BrowserFake &$ingestBrowser = null, ?LoopFake &$loop = null, ?TcpServerFake $server = null): array
{
    $output = '';
    $port ??= rand(9000, 9999);
    $payloadFile = __DIR__.'/test-payload';

    try {
        $write = file_put_contents($payloadFile, serialize([
            'listenOn' => "127.0.0.1:{$port}",
            'viaPhar' => $via === 'phar',
            'ingestDetailsBrowser' => $ingestDetailsBrowser,
            'ingestBrowser' => $ingestBrowser,
            'loop' => $loop,
            'server' => $server,
        ]));

        if ($write === false) {
            throw new RuntimeException('Unable to write test payload file.');
        }

        $process = Process::fromShellCommandline('php '.__DIR__.'/agent-wrapper.php')
            ->setTimeout($timeout);

        $process->mustRun(function (string $type, string $o) use ($until, $process, &$output) {
            $output .= $o;

            if ($until && $until($output)) {
                $process->stop(1);
            }
        });
    } catch (ProcessFailedException $e) {
        if ($e->getProcess()->getExitCode() === 143) {
            return [$output, null];
        }

        return [$output, $e];
    } catch (Throwable $e) {
        return [$output, $e];
    } finally {
        if (is_file($payloadFile)) {
            $payload = file_get_contents($payloadFile);

            if ($payload !== false) {
                $payload = unserialize($payload);

                if (is_array($payload)) {
                    /** @var array{ingestDetailsBrowser: BrowserFake, ingestBrowser: BrowserFake, loop: LoopFake}  $payload */
                    $ingestDetailsBrowser = $payload['ingestDetailsBrowser'];
                    $ingestBrowser = $payload['ingestBrowser'];
                    $loop = $payload['loop'];
                }
            }

            unlink($payloadFile);
        }
    }

    return [$output, null];
}
