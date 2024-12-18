<?php

namespace Laravel\Nightwatch\Factories;

use DateTimeZone;
use DateTimeZone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Logger as IlluminateLogger;
use Laravel\Nightwatch\Hooks\LogHandler;
use Laravel\Nightwatch\SensorManager;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * @internal
 */
final class Logger
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function __invoke(array $config): IlluminateLogger
    {
        return new IlluminateLogger(new Monolog(
            name: 'nightwatch',
            handlers: [
                new LogHandler($this->sensor),
            ],
            processors: [
                new PsrLogMessageProcessor('Y-m-d H:i:s.u'),
                // new JsonFormatter, // ditch stack trace? see other options.
            ],
            // timezone: new DateTimeZone('UTC'),
        ));
    }
}
