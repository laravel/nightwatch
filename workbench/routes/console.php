<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

use function React\Async\await;

Artisan::command('nightwatch:client {--once}', function () {
    $executionContext = 'request';
    $entries = fn () => [

        'requests' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId = rand(0, 1) ? 'v1.0.5' : null, // shared with other data points
                'server' => $server = 'web-01',
                'group' => str_repeat('a', 64),
                'trace_id' => $trace = Str::uuid()->toString(),
                'method' => 'GET',
                'route' => '/users/{user}',
                'path' => '/users/123',
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'ip' => '127.0.0.1',
                'duration' => 0,
                'status_code' => '200',
                'request_size_bytes' => 0,
                'response_size_bytes' => 0,
                'queries' => 0,
                'queries_duration' => 0,
                'lazy_loads' => 0,
                'lazy_loads_duration' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'mail_duration' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'notifications_duration' => 0,
                'outgoing_requests' => 0,
                'outgoing_requests_duration' => 0,
                'files_read' => 0,
                'files_read_duration' => 0,
                'files_written' => 0,
                'files_written_duration' => 0,
                'peak_memory_usage' => 0,
                'hydrated_models' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
            ],
        ],

        'queries' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'sql' => 'select count(*) from `users`',
                'category' => 'select',
                'location' => 'app/Http/Controllers/UserController.php:41',
                'duration' => 0,
                'connection' => 'mysql',
            ],
        ],

        'exceptions' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'class' => 'App\\Exceptions\\Whoops',
                'file' => 'app/Http/Controllers/UserController.php',
                'line' => 41,
                'message' => 'Whoops!',
                'code' => 0,
                'trace' => '...',
            ],
        ],

        'outgoing_requests' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'method' => 'POST',
                'url' => 'https://laravel.com',
                'duration' => 0,
                'request_size_bytes' => 0,
                'response_size_bytes' => 0,
                'status_code' => '200',
            ],
        ],

        'queued_jobs' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'job_id' => Str::uuid()->toString(),
                'class' => 'App\Jobs\MyJob',
                'connection' => 'redis',
                'queue' => 'high_priority',
            ],
        ],

        'cache_events' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'store' => 'redis',
                'key' => 'user:5',
                'type' => 'hit',
            ],
        ],

        'lazy_loads' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'model' => 'App\Models\User',
                'relation' => 'posts',
                'count' => 5,
                // connection / query?
            ],
        ],

        'logs' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'trace_id' => $trace,
                'level' => 'info',
                'message' => 'Hello world.',
                'context' => '{}',
                'extra' => '{}',
            ],
        ],

        'mail' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'mailer' => 'postmark',
                'class' => 'App\Mail\Welcome',
                'recipients' => 1,
                'duration' => 0,
                'queued' => false,
            ],
        ],

        'notifications' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'user' => rand(0, 1) ? '123' : null, // not shared.
                'class' => 'App\Notifications\InvoiceReminder',
                'recipients' => 1,
                'duration' => 0,
                'queued' => false,
                'channel' => 'slack',
            ],
        ],

        'commands' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'user' => rand(0, 1) ? '123' : null,
                'command' => 'inspire',
                'exit_code' => 0,
                'duration' => 0,
                'queries' => 0,
                'queries_duration' => 0,
                'lazy_loads' => 0,
                'lazy_loads_duration' => 0,
                'jobs_queued' => 0,
                'mail_queued' => 0,
                'mail_sent' => 0,
                'mail_duration' => 0,
                'notifications_queued' => 0,
                'notifications_sent' => 0,
                'notifications_duration' => 0,
                'outgoing_requests' => 0,
                'outgoing_requests_duration' => 0,
                'files_read' => 0,
                'files_read_duration' => 0,
                'files_written' => 0,
                'files_written_duration' => 0,
                'peak_memory_usage' => 0,
                'hydrated_models' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
            ],
        ],

        'job_attempts' => [
            [
                'deploy_id' => $deployId,
                'server' => $server,
                'group' => str_repeat('a', 64),
                'trace_id' => $trace,
                'user' => rand(0, 1) ? '123' : null,
                'started_at' => date('Y-m-d H:i:s', time()),
                'finished_at' => date('Y-m-d H:i:s', time() + 5),
                'job_id' => Str::uuid()->toString(),
                'attempt' => 1,
                'attempt_id' => Str::uuid()->toString(),
                'class' => 'App\\Jobs\\MyJob',
                'connection' => 'redis',
                'queue' => 'high_priority',
                'status' => 'processed',
                'duration' => 5,
            ],
        ],

    ];

    /* --------------------------------------------------- */

    $config = [
        'address' => '127.0.0.1',
        'port' => '8080',
        'connection_timeout' => 0.5, // seconds
        'timeout' => 0.5, // seconds
        // 'compression' => false,
    ];

    // Do this before we even attempt to create the connection. That way we
    // minimise the work done while the connection is open and do not even
    // open it if we cannot encode the value.
    // $value = Str::unwrap(json_encode($entries(), flags: JSON_THROW_ON_ERROR), '{', '}');
    $perSecond = collect([]);
    $durations = collect([]);
    $sent = 0;

    while (true) {
        $connector = new TcpConnector(null);

        $connector = new TimeoutConnector($connector, $config['connection_timeout']);

        $timeoutTimer = null;

        // TODO we probably need some way to send meta information about the
        // payload to the client. We should build in a headers mechanism, even
        // if we don't need it right now - because the client just pipes everything
        // it gets to the endpoint - even if it is not valid.
        // $headers = [
        //     'version: 1',
        // ];
        // $payload = implode("\n", $headers)."\nEND_HEADERS\n".json_encode($entries(), flags: JSON_THROW_ON_ERROR);

        $payload = json_encode($entries(), flags: JSON_THROW_ON_ERROR);

        $start = microtime(true);

        await($connector->connect('tcp://'.$config['address'].':'.$config['port'])
            ->then(function (ConnectionInterface $connection) use ($payload, $config, &$timeoutTimer): void {
                $timeoutTimer = Loop::addTimer($config['timeout'], function () use ($connection): void {
                    $this->error('Sending data timed out.');

                    $connection->close();
                });

                echo '.';

                $connection->end($payload);
            }, function (Throwable $e): void {
                $this->error('Connection error ['.$e->getMessage().'].');
            })->catch(function (Throwable $e): void {
                $this->error('Unknown error ['.$e->getMessage().'].');
            })->finally(function () use (&$timeoutTimer) {
                if ($timeoutTimer !== null) {
                    Loop::cancelTimer($timeoutTimer);
                }
            }));

        $duration = (int) ((microtime(true) - $start) * 1000);

        $sent++;
        $perSecond[$t = time()] = ($perSecond[$t] ?? 0) + 1;
        $durations[] = $duration;

        if (($sent % 100) === 0) {
            $this->line(PHP_EOL."Sent {$sent} payloads.");
            $this->line('Average per second: '.$perSecond->average());
            $this->line('Average duration: '.$durations->average().' ms');
            $this->line('Max duration: '.$durations->max().' ms');
            $this->line('Min duration: '.$durations->min().' ms');
            $sent = 0;
            $perSecond = collect();
            $durations = collect();
        }

        if ($this->option('once')) {
            return;
        }

        Sleep::for(rand(8, 400))->milliseconds();
    }
});
