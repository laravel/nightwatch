<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Console\Agent;
use Laravel\Nightwatch\Contracts\Client as ClientContract;
use Laravel\Nightwatch\Contracts\PeakMemoryProvider;
use Laravel\Nightwatch\Sensors\RequestSensor;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\LimitingServer;
use React\Socket\TcpServer;

final class NightwatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RequestSensor::class);
        $this->app->singleton(PeakMemoryProvider::class, PeakMemory::class);
        $this->configureRecordsCollection();
        $this->configureAgent();
        $this->configureClient();
        $this->mergeConfig();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublications();
            $this->registerCommands();
        }

        $this->registerSensors();
        $this->registerTcpIngest();
    }

    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');
    }

    protected function configureRecordsCollection(): void
    {
        $this->app->scoped(RecordCollection::class, fn () => new RecordCollection([
            'execution_parent' => [
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
                'cache_hits' => 0,
                'cache_misses' => 0,
                'hydrated_models' => 0,
            ],
            'requests' => new Collection(),
        ]));
    }

    protected function configureAgent(): void
    {
        $this->app->singleton(Agent::class, function (Container $app) {
            /** @var Config */
            $config = $app->make(Config::class);

            $loop = new StreamSelectLoop;

            $buffer = new RecordBuffer($config->get('nightwatch.agent.buffer_threshold'));

            $uri = $config->get('nightwatch.agent.address').':'.$config->get('nightwatch.agent.port');

            $server = new LimitingServer(
                new TcpServer($uri, $loop),
                $config->get('nightwatch.agent.connection_limit')
            );

            $connector = new Connector([
                'timeout' => $config->get('nightwatch.http.connection_timeout'), // TODO: test if this is the connection only or total duration.
            ], $loop);

            $ingest = new Ingest($app->make(ClientContract::class, [
                'loop' => $loop,
                'connector' => $connector,
            ]), $config->get('nightwatch.http.concurrent_request_limit'));

            return new Agent($buffer, $ingest, $server, $loop, $config->get('nightwatch.collector.timeout'));
        });
    }

    protected function configureClient(): void
    {
        $this->app->singleton(ClientContract::class, function (Container $app, array $args) {
            $args = $args + ['connector' => null, 'loop' => null];

            /** @var Config */
            $config = $app->make(Config::class);

            return new Client((new Browser($args['connector'], $args['loop']))
                ->withTimeout($config->get('nightwatch.agent.timeout'))
                ->withHeader('User-Agent', 'NightwatchAgent/1.0.0') // TODO use actual version instead of 1.0.0
                ->withHeader('Content-Type', 'application/json') // TODO: gzip...
                // ->withHeader('Content-Type', 'application/octet-stream')
                // ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
                ->withHeader('Authorization', "Bearer {$config->get('nightwatch.app_secret')}")
                ->withBase("https://5qdb6aj5xtgmwvytfyjb2kfmhi0gpiya.lambda-url.{$config->get('nightwatch.http.region')}.on.aws"));
        });
    }

    protected function registerPublications(): void
    {
        $this->publishes([
            __DIR__.'/../config/nightwatch.php' => $this->app->configPath('nightwatch.php'),
        ], ['nightwatch', 'nightwatch-config']);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            Console\Agent::class,
        ]);
    }

    protected function registerSensors(): void
    {
        // $this->callAfterResolving(Kernel::class, function (Kernel $kernel, Container $app) {
        //     if (method_exists($kernel, 'whenRequestLifecycleIsLongerThan')) {
        //         $kernel->whenRequestLifecycleIsLongerThan(-1, $app->make(RequestSensor::class));
        //     } else {
        //         // create alert? use another mechanism?
        //     }

        //     /** @var TcpIngest */
        //     $ingest = $app->make(TcpIngest::class);
        //     /** @var RecordCollection */
        //     $records = $app->make(RecordCollection::class);

        //     $ingest->write($records->toJson());
        // });
    }

    protected function registerTcpIngest(): void
    {
        $this->app->singleton(TcpIngest::class, function () {
            $uri = Config::get('nightwatch.agent.address').':'.Config::get('nightwatch.agent.port');

            return new TcpIngest();
        });
    }
}
