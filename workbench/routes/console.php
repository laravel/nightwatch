<?php

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Nightwatch\SensorManager;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Socket\TimeoutConnector;

use function React\Async\await;

Artisan::command('nightwatch:client {--times=} {--fast}', function () {
    // 'job' | 'request'
    // TODO: trim must **not** be mulitbyte.
    $executionContext = 'request';
    $entries = fn () => [
        'requests' => [
            [
                'timestamp' => date('Y-m-d H:i:s', time()),
                // shared with other data points.
                // TODO never `null`. always a string.
                // TODO make sure we `trim` the value to 500
                // TODO "alert" when exceeding
                // TODO should we trim whitespace across all of these?
                'deploy_id' => $deployId = rand(0, 1) ? 'v1.0.5' : '',
                // TODO: trim to 500 chars.
                // TODO: "alert" when exceeded by sending something to nightwatch
                'server' => $server = 'web-01',
                'group' => str_repeat('a', 64),
                'trace_id' => $trace = Str::uuid()->toString(),
                'method' => 'GET',
                'route' => '/users/{user}',
                'path' => '/users/123',
                // TODO: trim to 500
                // TODO: "alert" when exceeded by sending something to nightwatch
                'user' => rand(0, 1) ? '123' : '', // not shared.
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
                'user' => rand(0, 1) ? '123' : '', // not shared.
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
                'code' => 0, // int32. May be negative.
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
                // reasonable max
                'store' => 'redis',
                // max: 10_000
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
                'name' => 'inspire',
                'command' => 'inspire --help',
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

    $uri = Config::get('nightwatch.agent.address').':'.Config::get('nightwatch.agent.port');
    $timeout = Config::get('nightwatch.collector.timeout');
    $connectionTimeout = Config::get('nightwatch.collector.connection_timeout');
    $perSecond = collect([]);
    $durations = collect([]);
    $sent = 0;

    while (true) {
        $payload = json_encode($entries(), flags: JSON_THROW_ON_ERROR);
        $timeoutTimer = null;
        $start = microtime(true);

        $connector = new TimeoutConnector(new TcpConnector, $connectionTimeout);

        await($connector->connect($uri)
            ->then(function (ConnectionInterface $connection) use ($payload, $timeout, &$timeoutTimer) {
                $timeoutTimer = Loop::addTimer($timeout, function () use ($connection) {
                    $this->error('Sending data timed out.');

                    $connection->close();
                });

                echo '.';

                // TODO protocol?
                $connection->end($payload);
            }, function (Throwable $e) {
                $this->error('Connection error ['.$e->getMessage().'].');
            })->catch(function (Throwable $e) {
                $this->error('Unknown error ['.$e->getMessage().'].');
            })->finally(function () use (&$timeoutTimer) {
                if ($timeoutTimer !== null) {
                    Loop::cancelTimer($timeoutTimer);
                }
            }));

        // Stat collection...
        $duration = (int) ((microtime(true) - $start) * 1000);
        $sent++;
        $perSecond[$t = time()] = ($perSecond[$t] ?? 0) + 1;
        $durations[] = $duration;
        if (($sent % 100) === 0) {
            $this->line('Stats for the last 100 payloads:');
            $this->line('Average per second: '.$perSecond->average());
            $this->line('Average duration: '.$durations->average().' ms');
            $this->line('Max duration: '.$durations->max().' ms');
            $this->line('Min duration: '.$durations->min().' ms');
            $perSecond = collect();
            $durations = collect();
        }

        if ($this->option('times') && $sent == $this->option('times')) {
            return;
        }

        if (! $this->option('fast')) {
            Sleep::for(rand(8, 400))->milliseconds();
        }
    }
});

Artisan::command('nightwatch:hammer', function () {
    $queries = [
        'update `sites` set `deployment_status` = ? where `deployment_status` = ? and updated_at <= date_sub(now(), interval 15 minute)',
        'update `commands` set `status` = ? where `status` in (?, ?) and created_at <= date_sub(now(), interval 10 minute)',
        'update `backups` set `status` = ? where `status` = ? and `updated_at` <= ?',
        'update `sites` set `deployment_status` = ?, `sites`.`updated_at` = ? where `id` = ?',
        'update `jobs` set `status` = ? where `status` = ? and updated_at <= date_sub(now(), interval 10 minute)',
        'update `sites` set `deployment_status` = ?, `deployment_started_at` = ?, `sites`.`updated_at` = ? where `id` = ?',
        'select * from `workers` where `workers`.`site_id` = ? and `workers`.`site_id` is not null',
        'update `sites` set `last_deployment_id` = ?, `sites`.`updated_at` = ? where `id` = ?',
        'select * from `workers` where `workers`.`site_id` in (1231080, 1232023)',
        'update `php_versions` set `status` = ? where `status` in (?, ?) and updated_at <= date_sub(now(), interval 10 minute)',
        'update `jobs` set `status` = ? where `status` = ? and updated_at <= date_sub(now(), interval 10 minute)',
        'select `project_type`, count(`sites`.`id`) as aggregate from `sites` group by `project_type`',
        'select * from `sites` where (`sites`.`id` like ? or `sites`.`name` like ?) order by `sites`.`id` desc limit 26 offset 0',
        'select count(*) as aggregate from `sites` where (`sites`.`id` like ? or `sites`.`name` like ?)',
        'update `users` set `api_requests_count` = ?',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (727538, 735126, 739529, 744745, 760777, 776094, 793279)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (648695, 648699, 648817, 649050, 740206, 740827)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (243023, 255240, 448622, 448851, 451190, 500819, 521543, 522911, 696052, 696054)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (589608, 599538, 600292, 600512, 600643, 601197, 671614, 775960)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (623926, 655521, 743957)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (746307, 765117, 787481)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (530397, 678896)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (339305, 340197, 386760, 403269, 441128, 452942, 470422, 478585, 503116, 531938, 540901, 544261, 554118, 561635, 681545, 682787, 683124, 683159, 683166, 683898, 702387, 764405, 767992, 775008)',
        'select * from `workers` where `workers`.`site_id` in (2299328)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (83145, 172393, 240988, 244118, 307125, 326652, 381202, 390538, 408626, 443002, 447028, 452580, 482272, 492504, 512123, 527503, 549982, 557003, 573993, 611116, 621146, 649494, 652618, 654420, 684935, 723545, 730591, 738500, 763458, 773510, 788977)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (223460, 260687, 437638, 437639, 437641, 437643, 437772, 437775, 475096, 491071, 505472, 566069, 578809, 620224, 650392, 699482, 744373)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (512691, 525055, 543326, 543529, 546412, 547167, 565659, 573117, 582732, 587842, 589356, 591343, 591555, 593274, 593825, 595683, 603135, 603224, 603749, 607597, 622480, 633141, 635630, 644785, 647253, 648348, 648467, 650092, 662006, 667980, 669068, 675356, 684203, 684208, 684648, 706771, 712948, 714718, 717159, 717727, 722353, 724526, 734478, 743024, 744000, 747899, 749635, 752835, 754250, 759127, 759502, 769397, 777357, 778451, 780538, 781603, 782451, 785053, 786168, 791634, 791793)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (282363, 293320, 351153, 372224, 377758, 387589, 390815, 392273, 393987, 395128, 395341, 395381, 401051, 403821, 410635, 412046, 428026, 440326, 445228, 449329, 450151, 466369, 470588, 481169, 481731, 485850, 525114, 527601, 532410, 537228, 537971, 560158, 562306, 571065, 580208, 584601, 596913, 597599, 599918, 599934, 611426, 619578, 650105, 650532, 655820, 664518, 675168, 678849, 682756, 695969, 696024, 700732, 717469, 719832, 721126, 726596, 727692, 749296, 757897, 760239, 771552, 771758, 771759, 771987)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (703517, 785865, 785866)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (599658, 601493, 601590, 601592, 601841, 670031)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (382438, 382442, 413306, 478101, 504533, 574286, 601136, 641699, 666641, 706760, 729333, 738412, 763787, 771457)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (376714, 376716, 376717, 378006, 381415, 386577, 390483, 395305, 396269, 407980, 414792, 418542, 422951, 428266, 428277, 432915, 436268, 448002, 454143, 460758, 473078, 499718, 512443, 517536, 520879, 536417, 549065, 550460, 554909, 560220, 561341, 561343, 561344, 564572, 565766, 565767, 567357, 567358, 567359, 586610, 588562, 595679, 596566, 596567, 596568, 597533, 604307, 619049, 651175, 660922, 695965, 704542, 738227, 741580, 741581, 742372, 742605, 743085, 743102, 792919)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (93180, 100677, 112843, 114449, 133744, 178811, 183573, 318907, 362306, 385324, 503891, 565403, 583908, 590061, 603472, 646039, 650527, 660753, 677830, 705474, 707506, 734081, 738809, 769811, 769814)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` = ?',
        'select * from `backups` where `backups`.`backup_configuration_id` = ? and `backups`.`backup_configuration_id` is not null and `status` != ? order by `created_at` desc limit 4 offset 365',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` = ?',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` = ?',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (390768, 390874, 393921, 402033, 413343, 435290, 438760, 511661, 526144, 548097)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (167513, 273532, 292976, 299212, 343706, 389307, 421792, 477556, 492535, 501730, 510684, 585685, 610464, 660017, 700794, 736455, 748253, 793808)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (619096, 765002)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (587650, 695319, 717015)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (422663, 429057, 431012, 431302, 434046, 435586, 445549, 449572, 455934, 459392, 461322, 472288, 487608, 499727, 504623, 504638, 511255, 526852, 534578, 545186, 549071, 560774, 569844, 572780, 582546, 589860, 668263, 673999, 682947, 709089, 721741, 722817, 759235, 773400)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (41160, 162424, 238095, 257469, 262540, 396324, 400499, 400504, 409114, 477947, 516865, 521083, 564128, 580808, 595009, 670049, 670078, 683396, 732340, 755138, 756373, 769011, 769425, 769532, 790782, 792569)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (244634, 247789, 249194, 287214, 321009, 330157, 354353, 354830, 393230, 415849, 507456, 511248, 518466, 532521, 544995, 548012, 568958, 611405, 622244, 653033, 671550, 673409, 694562, 694579, 696293, 701858, 705155, 710514, 715233, 715587, 717204, 766940, 767270, 767271, 774266, 774293, 795897)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (72491, 127078, 595423)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (650893, 651266, 653001, 653015, 653984, 653985, 653986, 654299, 654410, 654818, 655154, 655672, 655699, 692785, 701824, 718179)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (711728, 732397, 732405, 732413, 732414, 732418, 732426, 735885, 744554)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (778554, 778605, 783905)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (707125, 752648)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (652700, 689174, 775152)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (28101, 86356, 244442, 399890, 407950, 602147, 729115)',
        'select * from `workers` where `workers`.`site_id` in (1690180)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (682794, 684107, 694541, 694606, 694610, 696776, 699961, 708376, 742068, 753481, 757263, 771813, 773408, 785398, 794591, 794593)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (475821, 478196, 697879)',
        'select * from `workers` where `workers`.`site_id` in (1690193)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (140400, 280554, 281215, 353420, 504357, 531122)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (590998, 705154, 748895)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (394007, 506511, 522254, 522282, 551606, 569329, 589818, 622390, 674966, 678261, 771391, 773561)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (606290, 650618, 657070, 657415, 657516, 658511, 659006, 660002, 665730, 682226, 695550, 773334)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (656782, 659623, 665202, 672828, 672841, 752135, 759931)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (490072, 590675, 606550, 665311, 675715, 752778)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (199079, 199080, 199967, 297836, 299554, 311028, 317595, 317627, 317639, 322560, 322942, 332827, 344714, 357770, 387498, 406279, 407220, 458277, 507237, 528768, 535149, 536425, 536428, 536524, 536528, 536529, 536549, 536551, 536554, 542936, 583071, 673392, 687206, 698758, 706322, 738269, 759834, 764333, 769005, 773450, 785569)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (264268, 264565, 270466, 273382, 276768, 277028, 278541, 284651, 285248, 285961, 285963, 285964, 286013, 286016, 286027, 286029, 286030, 286222, 286225, 286989, 286996, 288751, 290854, 315848, 321819, 322045, 341814, 351313, 351620, 351739, 368738, 380491, 380492, 390658, 414391, 424029, 425394, 428423, 428424, 428589, 463390, 499247, 504455, 504456, 509701, 513203, 514389, 523465, 533888, 537505, 544668, 560908, 570896, 573465, 573466, 580914, 592981, 596761, 596763, 608566, 665549, 698350, 713152, 727078, 727079, 739335, 764134, 775633, 776917, 777580, 795234)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (460685, 519209)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (504903, 563956, 718746, 719707, 723403, 732484, 733570, 733588, 734185, 734637, 735010, 736238, 758735, 762259, 764438, 782404, 788468, 788477, 792799, 794648, 794650, 794662, 794663)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (659398, 676584, 676796, 678441, 684934, 703431, 705664, 727916, 757719, 761196, 766828, 769953, 773142, 795465)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (205534, 210720, 217985, 269312, 269842, 378734, 380506, 381142, 392368, 408973, 413703, 418844, 438833, 460172, 496464, 507028, 552935, 561486, 606675, 673155, 738635, 739595, 744368, 749633, 783299, 789015)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (164762, 178986, 224953, 346300, 350146, 356381, 378725, 382653, 413722, 437394, 437626, 452907, 465053, 468310, 474202, 480837, 500657, 505602, 512147, 554400, 566337, 569583, 589527, 598862, 601546, 607423, 687686, 699824, 740285, 749184, 753215, 758271, 765701, 770661, 773852, 774188)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (473632, 674728, 721631)',
        'select * from `workers` where `workers`.`site_id` in (2179901)',
        'select * from `backups` where `backups`.`backup_configuration_id` = ? and `backups`.`backup_configuration_id` is not null and `status` != ? order by `created_at` desc limit 4 offset 336',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` = ?',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (582680, 582694)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (374352, 554285, 697805)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (617632, 661880, 662618, 662629, 662830, 663870, 693634, 725175, 743357, 744107, 744108, 744224, 748674, 750026, 751593, 752318, 752319, 753330, 753334, 753335, 757192, 766879, 766911, 767572, 773761, 774967, 777645, 778398, 778790, 781604, 782537, 783160, 784670, 784674, 789654, 789657, 793657, 796303)',
        'select `databases`.*, `database_user_databases`.`database_user_id` as `pivot_database_user_id`, `database_user_databases`.`database_id` as `pivot_database_id` from `databases` inner join `database_user_databases` on `databases`.`id` = `database_user_databases`.`database_id` where `database_user_databases`.`database_user_id` in (218057, 218395, 218402)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (771280)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (468085, 468441, 476567, 481049, 481050, 481274, 481418, 481419, 484732, 487105, 489369, 519233, 520106, 520187, 528218, 528317, 543075, 556005, 585396, 594438, 594439, 594440, 594443, 598560, 603120, 603121, 603123, 603124, 605855, 646920, 647453, 669301, 673882, 673884, 673885, 673887, 673889, 673890, 675335, 687184, 687186, 696625, 696626, 696629, 696630, 696641, 724185, 743654, 743655, 753660, 758619, 795889)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (776483)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (426120, 426129, 444700, 469321, 495571, 570497, 586706, 586710, 626551, 654766, 654778, 654779, 654783, 685940, 700741, 714404, 726915, 743952, 771960, 795557)',
        'select * from `backups` where `backups`.`backup_configuration_id` = ? and `backups`.`backup_configuration_id` is not null and `status` != ? order by `created_at` desc limit 4 offset 24',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (167494, 168683, 172327, 172331, 178010, 183589, 193023, 313859, 365147, 371686, 377224, 378066, 386438, 413173, 420286, 430116, 473627, 474469, 474493, 474496, 474540, 475174, 475180, 477126, 482607, 547676, 554181, 560103, 564479, 572259, 595012, 599696, 658514, 689026, 693923, 731628, 737227, 743929)',
        'select * from `workers` where `workers`.`site_id` = ? and `workers`.`site_id` is not null',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (736868)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (564423, 663494, 663496, 676805, 713573, 724599, 728499, 736513, 751877, 765700, 788150)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (538207, 538210, 538213, 538221, 538646, 538650, 539960, 545222, 545230, 560114, 568534, 572280, 572294, 572303, 572326, 572329, 572333, 572340, 572531, 572544, 591086, 595941, 598559, 602419, 602423, 603711, 603836, 692379, 731714, 731720, 731723, 731732, 731740, 731749, 731764, 732875, 732880, 735347, 752037, 752114, 752137, 752298, 753558, 755193, 755841, 756015)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (267082, 286568, 319376, 321877, 323649, 329491, 360256, 363231, 365065, 376933, 390367, 400254, 435125, 436928, 441109, 466696, 500614, 508168, 536924, 549720, 596652, 695255, 696045, 719786, 727884, 735637, 762511, 786677, 791269, 792965)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (116518, 116846, 136614, 163430, 193498, 239188, 253272, 338510, 338511, 360961, 512313, 516005, 555370, 560386, 561801, 576606, 591800, 611714, 680812, 694092, 697259, 699569, 743189)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (119286, 127678, 171648, 231414, 234393, 236053, 265620, 268948, 268951, 272536, 275947, 276586, 288888, 294757, 311459, 311460, 311824, 316407, 348603, 361103, 393680, 402062, 402561, 419554, 441703, 453461, 464940, 476716, 488755, 569420, 582101, 659192, 690338, 732335, 767085, 785898, 785900, 790613, 793972)',
        'select * from `backups` where `backups`.`backup_configuration_id` = ? and `backups`.`backup_configuration_id` is not null and `status` != ? order by `created_at` desc limit 4 offset 8760',
        'select `databases`.*, `database_user_databases`.`database_user_id` as `pivot_database_user_id`, `database_user_databases`.`database_id` as `pivot_database_id` from `databases` inner join `database_user_databases` on `databases`.`id` = `database_user_databases`.`database_id` where `database_user_databases`.`database_user_id` in (359496, 577811, 577813, 577815, 577818, 577819, 577820, 603581, 603583, 603584, 621945, 621946, 621952)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (572280, 572294, 572303, 572326, 572329, 572333, 572340, 572531, 572544, 591086, 602419, 602423, 603711, 603836, 732875, 732880, 735347, 752137, 752298, 755193, 755841)',
        'select * from `workers` where `workers`.`site_id` in (2179902)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (333845, 452400, 464080, 535878, 544191, 554902, 585694, 603377, 646032, 667836, 684757, 763955, 776006)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (760188, 786580, 790315, 790324, 790332, 790339, 790343, 790350, 790359, 790367, 795469)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (288590, 497734, 647518)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (104458, 195694, 229580, 362773, 388763, 390547, 390549, 572992, 646925, 646933, 648541, 718510)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (621084, 656569, 659999, 669941, 678042, 681814, 682885, 684165, 684998, 685330, 688654, 766785)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (341310, 341315, 341317, 341318, 342188, 358530, 386600, 486361, 534785, 569005)',
        'select `circles`.*, `circle_servers`.`server_id` as `pivot_server_id`, `circle_servers`.`circle_id` as `pivot_circle_id` from `circles` inner join `circle_servers` on `circles`.`id` = `circle_servers`.`circle_id` where `circle_servers`.`server_id` in (142140, 154134, 219421, 299524, 341645, 393434, 403204, 403744, 403861, 408836, 409965, 412292, 421668, 431371, 438245, 439498, 449338, 458945, 462870, 473604, 476298, 479285, 482375, 497916, 501499, 507857, 515636, 537237, 540585, 545917, 567973, 571620, 573925, 585350, 587838, 598506, 601156, 601853, 610781, 620812, 663128, 672941, 682791, 723192, 724944, 726980, 738213, 742757, 743305, 776725, 778491, 780718, 788526)',
    ];

    $cacheKeys = [
        'deployment:{event}',
        'DomainNameParser::publicSuffixList',
        'postmark:suppressions',
        'server:has-directory:{server}:{directory}',
        'server:has-directory:{server}:{directory}:timer',
        'site.count',
        'site:{site}-composer-lock',
        'job-exceptions:{job}',
        'github:username:{token}',
        'command:log:{site}:{command}',
        'deployment:log:{site}:{deployment}',
        'gitlab:username:{token}',
        'bitbucket:username:{token}',
        'deployment:log:{site}:{missing-deployment}',
        'certificate:csr:{certificate}',
    ];

    shuffle($queries);
    $db = DB::connection();

    $methods = ['GET', 'POST', 'PUT', 'DELETE'];

    $uris = [
        '/users',
        '/users/1',
        '/users/2',
        '/users/3',
        '/posts',
        '/posts/1',
        '/posts/2',
        '/posts/3',
    ];

    $status = [
        200,
        404,
        500,
    ];

    // $sensor = App::make(SensorManager::class);

    Http::preventStrayRequests();
    Http::fake(function () {
        return Http::response('ok');
    });

    $uuid = (string) Str::uuid();

    for ($i = 0; $i < 100; $i++) {
        // Event::dispatch(new QueryExecuted(
        //     sql: $queries[rand(0, count($queries) - 1)],
        //     bindings: [],
        //     time: rand(1, 9999999) / 1000,
        //     connection: $db,
        // ));
        Event::dispatch(new QueryExecuted(
            sql: 'select * from "users"',
            bindings: [],
            time: 5.2,
            connection: $db,
        ));

        Event::dispatch(new JobQueued(
            'database', 'default', $uuid, 'App\\Jobs\\MyJob', '{"uuid":"'.$uuid.'"}', 0
        ));

        report('Something happend. Not good!');

        // Event::dispatch(new CacheHit('database', $cacheKeys[array_rand($cacheKeys)], '', []));
        // Event::dispatch(new CacheMissed('database', $cacheKeys[array_rand($cacheKeys)], []));
        Event::dispatch(new CacheHit('database', 'users:123', '', []));
        Event::dispatch(new CacheMissed('database', 'users:123', []));

        // $sensor->outgoingRequest($start = rand(1, 999), $start + 1000, new Request(
        //     method: $methods[array_rand($methods)],
        //     uri: $uris[array_rand($uris)],
        //     body: str_repeat('a', rand(1, 100000)),
        // ), new Response(
        //     status: $status[array_rand($status)],
        //     body: str_repeat('a', rand(1, 100000)),
        // ));
        Http::get('https://laravel.com')->throw()->body();
    }
});
