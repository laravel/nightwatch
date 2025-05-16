<?php

use App\Jobs\MyJob;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
use Symfony\Component\Console\Input\StringInput;
use Tests\FakeJob;

uses(WithConsoleEvents::class);

beforeAll(function () {
    forceCommandExecutionState();
});

it('samples job attempts', function () {
    $ingest = fakeIngest();
    Compatibility::addHiddenContext('nightwatch_should_sample', false);

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }
    Artisan::call('queue:work', [
        '--max-jobs' => 10,
        '--sleep' => 0,
        '--stop-when-empty' => true,
        '--tries' => 1,
    ]);

    $ingest->assertWrittenTimes(0);
    expect(nightwatch()->ingest->buffer)->toHaveCount(0);

    Compatibility::addHiddenContext('nightwatch_should_sample', true);

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }
    Artisan::call('queue:work', [
        '--max-jobs' => 10,
        '--sleep' => 0,
        '--stop-when-empty' => true,
        '--tries' => 1,
    ]);

    $ingest->assertWrittenTimes(10);

    for ($i = 1; $i < 10; $i++) {
        $ingest->assertWrite($i, 'job-attempt:0.name', 'App\Jobs\MyJob');
    }

    expect(nightwatch()->ingest->buffer)->toHaveCount(0);
});

it('preparing for next job', function () {
    nightwatch()->clock->microtimeResolver = fn () => 5.5;
    nightwatch()->executionState->setId('previous');
    nightwatch()->executionState->executionPreview = 'previous';
    nightwatch()->executionState->timestamp = 0.0;

    Compatibility::addHiddenContext('nightwatch_should_sample', false);
    nightwatch()->prepareForJob(new class extends FakeJob
    {
        public function resolveName()
        {
            return 'current';
        }
    });

    expect(json_encode(nightwatch()->executionState->id()))->toBe('"previous"');
    expect(nightwatch()->executionState->executionPreview)->toBe('previous');
    expect(nightwatch()->executionState->timestamp)->toBe(0.0);

    Compatibility::addHiddenContext('nightwatch_should_sample', true);
    Str::createUuidsUsingSequence([
        '1CF1F203-73A5-4E9D-8662-12E1C712F130',
    ]);
    nightwatch()->prepareForJob(new class extends FakeJob
    {
        public function resolveName()
        {
            return 'current';
        }
    });

    expect(json_encode(nightwatch()->executionState->id()))->toBe('"1CF1F203-73A5-4E9D-8662-12E1C712F130"');
    expect(nightwatch()->executionState->executionPreview)->toBe('current');
    expect(nightwatch()->executionState->timestamp)->toBe(5.5);
});

it('can configure command sampling', function () {
    nightwatch()->config['sampling']['commands'] = 0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toBe(0);

    nightwatch()->config['sampling']['commands'] = 0.25;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toEqualWithDelta(250, 50);

    nightwatch()->config['sampling']['commands'] = 0.5;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toEqualWithDelta(500, 50);

    nightwatch()->config['sampling']['commands'] = 1.0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toBe(1000);
});

it('samples preparing for command', function () {
    nightwatch()->shouldSample = false;

    nightwatch()->executionState->name = 'previous';
    nightwatch()->executionState->executionPreview = 'previous';

    nightwatch()->prepareForCommand('current');

    expect(nightwatch()->executionState->name)->toBe('previous');
    expect(nightwatch()->executionState->executionPreview)->toBe('previous');

    nightwatch()->shouldSample = true;

    nightwatch()->prepareForCommand('current');

    expect(nightwatch()->executionState->name)->toBe('current');
    expect(nightwatch()->executionState->executionPreview)->toBe('current');
});

it('samples commands', function () {
    Artisan::command('app:build', function () {
        return 0;
    });
    nightwatch()->config['sampling']['commands'] = 0;
    nightwatch()->configureSampling('commands');

    // bootstrap the test to ensure everything needed is in place, such as artisan
    Artisan::handle($input = new StringInput('app:build'));

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->prepareForCommand('app:build');
        nightwatch()->command($input, 0);
    }

    expect(json_decode(nightwatch()->ingest->buffer->pull()->rawPayload()))->toBe([]);

    nightwatch()->config['sampling']['commands'] = 1.0;
    nightwatch()->configureSampling('commands');

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->prepareForCommand('app:build');
        nightwatch()->command($input, 0);
    }

    $commands = collect(json_decode(nightwatch()->ingest->buffer->pull()->rawPayload()));
    expect($commands)->toHaveCount(10);
    expect($commands->pluck('name')->every(fn ($name) => $name === 'app:build'))->toBeTrue();
});
