<?php

namespace Laravel\Package;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Package\Console\Agent;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Http\Browser;
use React\Socket\Connector;
use React\Socket\LimitingServer;
use React\Socket\ServerInterface as Server;
use React\Socket\TcpServer;

class LaravelPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('laravel.nightwatch.loop', StreamSelectLoop::class);

        $this->app->when(Agent::class)
            ->needs(Server::class)
            ->give(static function (Container $app): Server {
                /** @var LoopInterface */
                $loop = $app->make('laravel.nightwatch.loop');
                /** @var Config */
                $config = $app->make(Config::class);

                $server = new TcpServer(
                    'tcp://'.$config->get('laravel-package.agent.server.address').':'.$config->get('laravel-package.agent.server.port'),
                    $loop,
                    [ /* @see https://www.php.net/manual/en/context.socket.php */ ],
                );

                return new LimitingServer(
                    $server,
                    $app->make('config')->get('laravel-package.agent.server.connection_limit'),
                );
            });

        $this->app->when(Agent::class)
            ->needs(Buffer::class)
            ->give(static function (Container $app): Buffer {
                /** @var Config */
                $config = $app->make(Config::class);

                return new Buffer($config->get('laravel-package.agent.buffer_threshold'));
            });

        $this->app->when(Agent::class)
            ->needs(Ingest::class)
            ->give(static function (Container $app): Ingest {
                /** @var LoopInterface */
                $loop = $app->make('laravel.nightwatch.loop');
                /** @var Config */
                $config = $app->make(Config::class);

                $connector = new Connector([
                    'timeout' => $config->get('laravel-package.agent.connection_timeout'), // TODO: test if this is the connection only or total duration.
                ], $loop);

                $browser = (new Browser(
                    ), $app->make('laravel.nightwatch.loop')
                ))->withTimeout(
                    $app->make('config')->get('laravel-package.agent.timeout')
                )->withHeader(
                    'User-Agent', 'NightwatchAgent/1.0.0' // TODO use actual version instead of 1.0.0
                )->withHeader(
                    'Nightwatch-App-Id', $app->make('config')->get('laravel-package.app_id')
                );

                return new Ingest($browser, $app->make('config')->get('laravel-package.agent.concurrent_request_limit'));
            });

        $this->app->when(Agent::class)
            ->needs(ConnectionManager::class)
            ->give(function () {
                /** @var LoopInterface */
                $loop = $app->make('laravel.nightwatch.loop');
                /** @var Config */
                $config = $app->make(Config::class);

                return new ConnectionManager($loop, $config->get('laravel-package.agent.server.timeout'));
            });

        $this->app->when(Agent::class)
            ->needs('$')

        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-package.php', 'laravel-package'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::group([
            'domain' => config('laravel-package.domain', null),
            'middleware' => config('laravel-package.middleware', 'web'),
            'namespace' => 'Laravel\Package\Http\Controllers',
            'prefix' => config('laravel-package.path'),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-package');
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-package-migrations');

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/laravel-package'),
            ], ['laravel-package-assets', 'laravel-assets']);

            $this->publishes([
                __DIR__.'/../config/laravel-package.php' => config_path('laravel-package.php'),
            ], 'laravel-package-config');
        }
    }

    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\InstallCommand::class,
            ]);
        }
    }
}
