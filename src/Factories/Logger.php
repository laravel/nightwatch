<?php

namespace Laravel\Nightwatch\Factories;

use DateTimeZone;
use Laravel\Nightwatch\Hooks\LogHandler;
use Laravel\Nightwatch\Hooks\LogRecordProcessor;
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

    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Monolog
    {
        return new Monolog(
            name: 'nightwatch',
            handlers: [
                new LogHandler($this->sensor),
            ],
            processors: [
                new LogRecordProcessor('Y-m-d H:i:s.u (e)'),
                new PsrLogMessageProcessor('Y-m-d H:i:s.u (e)'),
                // new JsonFormatter, // ditch stack trace? see other options.
            ],
            // timezone: new DateTimeZone('UTC'),
        );
    }
}
