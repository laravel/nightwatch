<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Pest\Laravel\travelTo;

$_ENV['APP_BASE_PATH'] = realpath(__DIR__.'/../workbench/').'/';

uses(Tests\TestCase::class)->in('Unit', 'Feature');

function nightwatch(): Core
{
    return app(Core::class);
}

function requestState(): RequestState
{
    return nightwatch()->executionState;
}

function commandState(): CommandState
{
    return nightwatch()->executionState;
}

function forceRequestExecutionState(): void
{
    Env::getRepository()->set('NIGHTWATCH_FORCE_REQUEST', '1');
    Env::getRepository()->clear('NIGHTWATCH_FORCE_COMMAND');
}

function forceCommandExecutionState(): void
{
    Env::getRepository()->set('NIGHTWATCH_FORCE_COMMAND', '1');
    Env::getRepository()->clear('NIGHTWATCH_FORCE_REQUEST');
}

function setExecutionStart(CarbonImmutable $timestamp): void
{
    syncClock($timestamp);
    nightwatch()->executionState->stageDurations[ExecutionStage::Bootstrap->value] = 0;
    nightwatch()->executionState->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
    nightwatch()->executionState->stage = match (nightwatch()->executionState::class) {
        RequestState::class => ExecutionStage::BeforeMiddleware,
        CommandState::class => ExecutionStage::Action,
    };
}

function syncClock(DateTimeInterface $timestamp): void
{
    nightwatch()->executionState->timestamp = (float) $timestamp->format('U.u');
    travelTo($timestamp);
}

function setDeploy(string $deploy): void
{
    nightwatch()->executionState->deploy = $deploy;
}

function setServerName(string $server): void
{
    nightwatch()->executionState->server = $server;
}

function setTraceId(string $traceId): void
{
    nightwatch()->executionState->trace = $traceId;
    Compatibility::addHiddenContext('nightwatch_trace_id', $traceId);
}

function setExecutionId(string $executionId): void
{
    nightwatch()->executionState->setId($executionId);
}

function setPeakMemory(int $value): void
{
    nightwatch()->executionState->peakMemoryResolver = fn () => $value;
}

function setLaravelVersion(string $version): void
{
    nightwatch()->executionState->laravelVersion = $version;
}

function setPhpVersion(string $version): void
{
    nightwatch()->executionState->phpVersion = $version;
}

function fakeIngest(?Ingest $fake = null): FakeIngest
{
    nightwatch()->sensor->flush();

    return nightwatch()->ingest = nightwatch()->sensor->ingest = $fake ?? new FakeIngest;
}

function prependListener(string $event, callable $listener): void
{
    $listeners = Event::getRawListeners()[$event] ?? [];

    Event::forget($event);

    collect([$listener, ...$listeners])->each(fn ($listener) => Event::listen($event, $listener));
}

function fixturePath(string $path): string
{
    return join_paths(__DIR__, 'fixtures', $path);
}

class MyEvent
{
    use Dispatchable;
}

class MyQueuedMail extends Mailable
{
    public function content(): Content
    {
        travelTo(now()->addMicroseconds(2500));

        return new Content(
            view: 'mail',
        );
    }
}
