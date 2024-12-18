<?php

namespace Laravel\Nightwatch\Hooks;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

class LogRecordProcessor implements ProcessorInterface
{
    public function __construct(private string $format)
    {
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
            Log::critical('[nightwatch] '.$e->getMessage());
        }

        return $record;
    }
}

