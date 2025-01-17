<?php

namespace Laravel\Nightwatch\Hooks;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * @internal
 */
final class LogRecordProcessor implements ProcessorInterface
{
    private FormatterInterface $formatter;

    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
        private string $dateFormat,
    ) {
        //
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            /** @var array<string, mixed> */
            $formatted = $this->formatter()->format($record);

            return $record->with(
                message: $formatted['message'] ?? '',
                context: $formatted['context'] ?? [],
                level: $record->level,
                channel: $formatted['channel'] ?? '',
                datetime: $record->datetime,
                extra: $formatted['extra'] ?? [],
            );
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }

        return $record;
    }

    private function formatter(): FormatterInterface
    {
        return $this->formatter ??= new class($this->dateFormat) extends NormalizerFormatter
        {
            protected function formatDate(DateTimeInterface $date): string
            {
                return parent::formatDate(
                    DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('UTC'))
                );
            }
        };
    }
}
