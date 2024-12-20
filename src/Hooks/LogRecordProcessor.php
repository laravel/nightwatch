<?php

namespace Laravel\Nightwatch\Hooks;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * @internal
 */
final class LogRecordProcessor implements ProcessorInterface
{
    /**
     * @param  Core<RequestState>|Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
        private string $format,
    ) {
        //
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $context = $record->context;

            foreach ($context as $key => $value) {
                if ($value instanceof DateTimeInterface) {
                    $context[$key] = DateTimeImmutable::createFromInterface($value)
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format($this->format);
                }
            }

            return $record->with(context: $context);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        return $record;
    }
}
