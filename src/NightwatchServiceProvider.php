<?php

namespace Laravel\Package;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Laravel\Package\Console\Agent;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\LimitingServer;
use React\Socket\TcpServer;

class NightwatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Agent::class, function (Container $app): Agent {
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

            $browser = (new Browser($connector, $loop))
                ->withTimeout($config->get('nightwatch.agent.timeout'))
                ->withHeader('User-Agent', 'NightwatchAgent/1.0.0') // TODO use actual version instead of 1.0.0
                ->withHeader('Content-Type', 'application/json') // TODO: gzip...
                // ->withHeader('Content-Type', 'application/octet-stream')
                // ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Nightwatch-App-Id', $config->get('nightwatch.app_id'))
                ->withHeader('Authorization', "Bearer {$config->get('nightwatch.app_secret')}")
                ->withBase("https://5qdb6aj5xtgmwvytfyjb2kfmhi0gpiya.lambda-url.{$config->get('nightwatch.http.region')}.on.aws");

            $ingest = new Ingest($browser, $config->get('nightwatch.http.concurrent_request_limit'));

            return new Agent($buffer, $ingest, $server, $loop);
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/nightwatch.php', 'nightwatch'
        );
    }

    public function boot(): void
    {
        //
    }
}
