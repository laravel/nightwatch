<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Tests\FakeIngest;

use function Illuminate\Filesystem\join_paths;
use function Orchestra\Testbench\Pest\tearDown;
use function Pest\Laravel\travelTo;

$_ENV['APP_BASE_PATH'] = realpath(__DIR__.'/../workbench/').'/';

tearDown(function () {
    Str::createUuidsNormally();
});

pest()->extends(Tests\TestCase::class)->beforeEach(function () {
    nightwatch()->clock->microtimeResolver = fn () => (float) now()->format('U.u');
    Config::set('nightwatch.error_log_channel', 'null');
});

function nightwatch(): Core
{
    return app(Core::class);
}

function requestState(): RequestState
{
    return nightwatch()->state;
}

function commandState(): CommandState
{
    return nightwatch()->state;
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
    nightwatch()->state->stageDurations[ExecutionStage::Bootstrap->value] = 0;
    nightwatch()->state->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
    nightwatch()->state->stage = match (nightwatch()->state::class) {
        RequestState::class => ExecutionStage::BeforeMiddleware,
        CommandState::class => ExecutionStage::Action,
    };
}

function syncClock(DateTimeInterface $timestamp): void
{
    nightwatch()->state->timestamp = (float) $timestamp->format('U.u');
    travelTo($timestamp);
}

function setDeploy(string $deploy): void
{
    nightwatch()->state->deploy = $deploy;
}

function setServerName(string $server): void
{
    nightwatch()->state->server = $server;
}

function setTraceId(string $traceId): void
{
    nightwatch()->state->trace = $traceId;
    context()->addHidden('nightwatch_trace_id', $traceId);
}

function setExecutionId(string $executionId): void
{
    nightwatch()->state->id = $executionId;
}

function setPeakMemory(int $value): void
{
    nightwatch()->state->peakMemoryResolver = fn () => $value;
}

function setLaravelVersion(string $version): void
{
    nightwatch()->state->laravelVersion = $version;
}

function setPhpVersion(string $version): void
{
    nightwatch()->state->phpVersion = $version;
}

function fakeIngest(): FakeIngest
{
    return nightwatch()->ingest = new FakeIngest;
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
