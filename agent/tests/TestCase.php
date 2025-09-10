<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

use function debug_backtrace;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function is_array;
use function is_file;
use function is_string;
use function rand;
use function serialize;
use function str_replace;
use function substr;
use function unlink;
use function unserialize;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  'source'|'phar'  $via
     * @param  (callable(string): bool)  $until
     * @return array{0: string, 1: Throwable|null}
     *
     * @param-out  BrowserFake  $ingestDetailsBrowser
     * @param-out  BrowserFake  $ingestBrowser
     * @param-out  LoopFake  $loop
     * @param-out  TcpServerFake  $server
     */
    protected function runAgent(
        string $via,
        ?callable $until = null,
        float $timeout = 0.5,
        ?BrowserFake &$ingestDetailsBrowser = null,
        ?BrowserFake &$ingestBrowser = null,
        ?LoopFake &$loop = null,
        ?TcpServerFake &$server = null,
        bool $silent = false,
        bool $quiet = false,
    ): array {
        $output = '';
        $port = rand(9000, 9999);
        $payloadFile = __DIR__.'/test-payload';

        try {
            $write = file_put_contents($payloadFile, serialize([
                'listenOn' => "127.0.0.1:{$port}",
                'viaPhar' => $via === 'phar',
                'ingestDetailsBrowser' => $ingestDetailsBrowser,
                'ingestBrowser' => $ingestBrowser,
                'loop' => $loop,
                'server' => $server,
                'silent' => $silent,
                'quiet' => $quiet,
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
                        /** @var array{ingestDetailsBrowser: BrowserFake, ingestBrowser: BrowserFake, loop: LoopFake, server: TcpServerFake, silent: bool, quiet: bool }  $payload */
                        $ingestDetailsBrowser = $payload['ingestDetailsBrowser'];
                        $ingestBrowser = $payload['ingestBrowser'];
                        $loop = $payload['loop'];
                        $server = $payload['server'];
                        $silent = $payload['silent'];
                        $quiet = $payload['quiet'];
                    }
                }

                unlink($payloadFile);
            }
        }

        return [$output, null];
    }

    protected function functionName(): string
    {
        return static::class.'::'.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
    }

    protected function assertLogMatches(string $expected, string $actual, bool $silent = false, bool $quiet = false): self
    {
        if (! $quiet && ! $silent) {
            $expected = "{date} {info} Nightwatch agent initiated: Listening on \[127.0.0.1:\d{4}\]\n{$expected}";
        }
        $expected = str_replace('{date}', '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}', $expected);
        $expected = str_replace('{duration}', '\[\d(\.\d{1,3})?s\]', $expected);
        $expected = str_replace('{info}', '\[INFO\]', $expected);
        $expected = str_replace('{error}', '\[ERROR\]', $expected);

        $this->assertMatchesRegularExpression("#^{$expected}$#", $actual);

        return $this;
    }

    public static function tokenHash(): string
    {
        $refreshToken = $_SERVER['NIGHTWATCH_TOKEN'] ?? '';
        if (! is_string($refreshToken)) {
            throw new RuntimeException('NIGHTWATCH_TOKEN invalid');
        }

        return substr(hash('xxh128', $refreshToken), 0, 7);
    }
}
