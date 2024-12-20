<?php

namespace Laravel\Nightwatch\Factories;

use DateTimeZone;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Hooks\LogHandler;
use Laravel\Nightwatch\Hooks\LogRecordProcessor;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * @internal
 */
final class Logger
{
    /**
     * @param  Core<RequestState>|Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
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
                new LogHandler($this->nightwatch),
            ],
            processors: [
                new LogRecordProcessor($this->nightwatch, 'Y-m-d H:i:s.uP'),
                new PsrLogMessageProcessor('Y-m-d H:i:s.uP'),
            ],
            timezone: new DateTimeZone('UTC'),
        );
    }
}
