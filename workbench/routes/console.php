<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

use function React\Async\await;

Artisan::command('nightwatch:client {--once}', function () {
    $entries = fn () => [
        'execution_context' => $executionContext = 'request',
        'request' => [
            'deploy_id' => $deployId = 'v1.0.5', // shared with other data points
            'trace_id' => $trace = Str::uuid()->toString(),
            'server' => $server = 'web-01',
            'user' => '123', // not shared.

            'timestamp' => date('Y-m-d H:i:s', time()),
            'group' => str_repeat('a', 32),
            'method' => 'GET',
            'route' => '/users/{user}',
            'path' => '/users/123',
            'ip' => '127.0.0.1',
            'duration' => 0,
            'status_code' => '200',
            'request_size_bytes' => 0,
            'response_size_bytes' => 0,
            'query_count' => 0,
            'query_duration' => 0,
            'lazy_loaded_query_count' => 0,
            'lazy_loaded_query_duration' => 0,
            'job_queued_count' => 0,
            'mail_unqueued_count' => 0,
            'mail_unqueued_duration' => 0,
            'mail_queued_count' => 0,
            'notification_unqueued_count' => 0,
            'notification_unqueued_duration' => 0,
            'notification_queued_count' => 0,
            'outgoing_request_count' => 0,
            'outgoing_request_duration' => 0,
            'files_read_count' => 0,
            'files_read_duration' => 0,
            'files_written_count' => 0,
            'files_written_duration' => 0,
            'peak_memory_usage' => 0,
            'hydrated_model_count' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
        ],
        'queries' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'sql' => 'select count(*) from `users`',
                'category' => 'select',
                'location' => 'app/Http/Controllers/UserController.php:41',
                'duration' => 0,
                'connection' => 'mysql',
            ],
            // ...
        ],
        'exceptions' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'class' => 'App\\Exceptions\\Whoops',
                'file' => 'app/Http/Controllers/UserController.php',
                'line' => 41,
                'message' => 'Whoops!',
                'code' => 0,
                'trace' => '...',
            ],
            // ...
        ],
        'outgoing_requests' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'method' => 'POST',
                'url' => 'https://laravel.com',
                'duration' => 0,
                'request_size_bytes' => 0,
                'response_size_bytes' => 0,
                'status_code' => '200',
            ],
            // ...
        ],
        'queued_jobs' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'job_id' => Str::uuid()->toString(),
                'class' => 'App\Jobs\MyJob',
                'connection' => 'redis',
                'queue' => 'high_priority',
            ],
            // ...
        ],
        'cache_events' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'store' => 'redis',
                'key' => 'user:5',
                'type' => 'hit',
            ],
            // ...
        ],
        'lazy_loads' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'model' => 'App\Models\User',
                'relation' => 'posts',
                'count' => 5,
                // connection / query?
            ],
            // ...
        ],
        'logs' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'level' => 'info',
                'message' => 'Hello world.',
                'context' => '{}',
                'extra' => '{}',
            ],
            // ...
        ],
        'mail' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'mailer' => 'postmark',
                'group' => str_repeat('a', 32),
                'class' => 'App\Mail\Welcome',
                'recipients' => 1,
                'duration' => 0,
                'queued' => false,
            ],
            // ...
        ],
        'notifications' => [
            [
                'deploy_id' => $deployId,
                'trace_id' => $trace,
                'execution_context' => $executionContext,
                'execution_id' => $trace,
                'server' => $server,
                'user' => '123',

                'timestamp' => date('Y-m-d H:i:s', time()),
                'group' => str_repeat('a', 32),
                'class' => 'App\Notifications\InvoiceReminder',
                'recipients' => 1,
                'duration' => 0,
                'queued' => false,
                'channel' => 'slack',
            ],
            // ...
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
        $connector = new TimeoutConnector(new TcpConnector, $config['connection_timeout']);

        // TODO: custom protocol?
        $payload = json_encode($entries(), flags: JSON_THROW_ON_ERROR);

        $connection = null;
        $start = hrtime(true);

        try {
            $connection = await($connector->connect($config['address'].':'.$config['port']));

            echo '.';

            $connection->end('foo');
        } catch (Throwable $e) {
            $this->error('Unable to establish connection ['.$e->getMessage().'].');
        }

        $connection?->close();

        $duration = (hrtime(true) - $start) / 1000000;

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

        // Sleep::for(rand(8, 400))->milliseconds();
        // Sleep::for(1000)->milliseconds();
    }
});
