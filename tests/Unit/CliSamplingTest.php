<?php

use App\Jobs\MyJob;
use Illuminate\Foundation\Application;
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
    Config::set('queue.default', 'database');
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
    expect(nightwatch()->state->records)->toHaveCount(0);

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

    expect(nightwatch()->state->records)->toHaveCount(1);
    $ingest->write(nightwatch()->state->records->pull());
    $ingest->assertLatestWrite('cache-event:0.key', 'illuminate:queue:restart');
    $ingest->assertLatestWrite('job-attempt:*', []);
})->skip(version_compare(Application::VERSION, '11.0.0', '<'), 'Laravel 10 support is pending');

it('preparing for next job', function () {
    Config::set('queue.default', 'database');
    nightwatch()->clock->microtimeResolver = fn () => 5.5;
    nightwatch()->state->setId('previous');
    nightwatch()->state->executionPreview = 'previous';
    nightwatch()->state->timestamp = 0.0;

    Compatibility::addHiddenContext('nightwatch_should_sample', false);
    nightwatch()->prepareForJob(new class extends FakeJob
    {
        public function resolveName()
        {
            return 'current';
        }
    });

    expect(json_encode(nightwatch()->state->id()))->toBe('"previous"');
    expect(nightwatch()->state->executionPreview)->toBe('previous');
    expect(nightwatch()->state->timestamp)->toBe(0.0);

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

    expect(json_encode(nightwatch()->state->id()))->toBe('"1CF1F203-73A5-4E9D-8662-12E1C712F130"');
    expect(nightwatch()->state->executionPreview)->toBe('current');
    expect(nightwatch()->state->timestamp)->toBe(5.5);
})->skip(version_compare(Application::VERSION, '11.0.0', '<'), 'Laravel 10 support is pending');

it('can configure command sampling', function () {
    nightwatch()->sampling['commands'] = 0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toBe(0);

    nightwatch()->sampling['commands'] = 0.25;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toEqualWithDelta(250, 50);

    nightwatch()->sampling['commands'] = 0.5;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('commands');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toEqualWithDelta(500, 50);

    nightwatch()->sampling['commands'] = 1.0;
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

    nightwatch()->state->name = 'previous';
    nightwatch()->state->executionPreview = 'previous';

    nightwatch()->prepareForCommand('current');

    expect(nightwatch()->state->name)->toBe('previous');
    expect(nightwatch()->state->executionPreview)->toBe('previous');

    nightwatch()->shouldSample = true;

    nightwatch()->prepareForCommand('current');

    expect(nightwatch()->state->name)->toBe('current');
    expect(nightwatch()->state->executionPreview)->toBe('current');
});

it('samples commands', function () {
    Artisan::command('app:build', function () {
        return 0;
    });
    nightwatch()->sampling['commands'] = 0;
    nightwatch()->configureSampling('commands');

    // bootstrap the test to ensure everything needed is in place, such as artisan
    Artisan::handle($input = new StringInput('app:build'));

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->prepareForCommand('app:build');
        nightwatch()->command($input, 0);
    }

    expect(json_decode(nightwatch()->state->records->pull()->rawPayload()))->toBe([]);

    nightwatch()->sampling['commands'] = 1.0;
    nightwatch()->configureSampling('commands');

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->prepareForCommand('app:build');
        nightwatch()->command($input, 0);
    }

    $commands = collect(json_decode(nightwatch()->state->records->pull()->rawPayload()));
    expect($commands)->toHaveCount(10);
    expect($commands->pluck('name')->every(fn ($name) => $name === 'app:build'))->toBeTrue();
});
