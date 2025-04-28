<?php

use App\Jobs\MyJob;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Compatibility;
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
