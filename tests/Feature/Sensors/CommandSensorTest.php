<?php

namespace Tests\Feature\Sensors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Compatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

use function array_shift;
use function hash;
use function json_decode;
use function now;

class CommandSensorTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_can_ingest_commands(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('app:build {destination} {--force} {--compress}', function () {
            DB::table('users')->get();

            Date::setTestNow(now()->addMicroseconds(1234567));

            return 3;
        });

        $status = Artisan::handle($input = new StringInput('app:build path/to/output --force'));
        Artisan::terminate($input, $status);

        $this->assertSame(3, $status);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('command:*', [
            [
                'v' => 1,
                't' => 'command',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'app:build'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'class' => 'Illuminate\Foundation\Console\ClosureCommand',
                'name' => 'app:build',
                'command' => 'app:build path/to/output --force',
                'exit_code' => 3,
                'duration' => 1234567,
                'bootstrap' => 0,
                'action' => 1234567,
                'terminating' => 0,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => 1,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);
        $ingest->assertLatestWrite('query:0.execution_preview', 'app:build');
    }

    public function test_it_modifies_status_code_to_value_in_range_of_0_255(): void
    {
        $ingest = $this->fakeIngest();
        $status = [
            -1,
            0,
            1,
            254,
            255,
            256,
        ];
        Artisan::command('app:build {destination} {--force} {--compress}', function () use (&$status) {
            return array_shift($status);
        });

        $run = function () {
            $status = Artisan::handle($input = new StringInput('app:build path/to/output --force'));
            Artisan::terminate($input, $status);

            return $status;
        };

        $this->assertSame(-1, $run());
        $ingest->assertLatestWrite('command:0.exit_code', 255);

        $this->assertSame(0, $run());
        $ingest->assertLatestWrite('command:0.exit_code', 0);

        $this->assertSame(1, $run());
        $ingest->assertLatestWrite('command:0.exit_code', 1);

        $this->assertSame(254, $run());
        $ingest->assertLatestWrite('command:0.exit_code', 254);

        $this->assertSame(255, $run());
        $ingest->assertLatestWrite('command:0.exit_code', 255);

        $this->assertSame(256, $run());
        $ingest->assertLatestWrite('command:0.exit_code', 255);
    }

    public function test_it_captures_context(): void
    {
        $this->markTestSkippedUnless(Compatibility::$contextExists, 'This test requires the Laravel Context.');

        $ingest = $this->fakeIngest();
        $model = User::factory()->create();
        Artisan::command('test-context', function () use ($model) {
            Context::add('string', 'value');
            Context::add('integer', 123);
            Context::add('float', 123.456);
            Context::add('boolean', true);
            Context::add('null', null);
            Context::add('list', [1, 2.0, 'three']);
            Context::add('associative', ['key' => 'value']);
            Context::add('object', (object) ['key' => 'value']);
            Context::add('model', $model);
        });

        $status = Artisan::handle($input = new StringInput('test-context'));
        Artisan::terminate($input, $status);

        $this->assertSame(0, $status);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('command:0.context', function ($context) use ($model) {
            $context = json_decode($context, true);
            $this->assertSame('value', $context['string']);
            $this->assertSame(123, $context['integer']);
            $this->assertSame(123.456, $context['float']);
            $this->assertTrue($context['boolean']);
            $this->assertNull($context['null']);
            $this->assertSame([1, 2.0, 'three'], $context['list']);
            $this->assertSame(['key' => 'value'], $context['associative']);
            $this->assertSame(['key' => 'value'], $context['object']);
            $this->assertSame($model->getKey(), $context['model']['id']);

            return true;
        });
    }

    public function test_it_only_captures_the_first_command_that_runs(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('child', function () {
            return 99;
        });
        Artisan::registerCommand($this->app[ParentCommand::class]);

        $run = function () {
            $status = Artisan::handle($input = new StringInput('parent'));

            Artisan::terminate($input, $status);

            return $status;
        };

        $this->assertSame(0, $run());
        $ingest->assertLatestWrite('command:*', [
            [
                'v' => 1,
                't' => 'command',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'parent'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'class' => 'Tests\Feature\Sensors\ParentCommand',
                'name' => 'parent',
                'command' => 'parent',
                'exit_code' => 0,
                'duration' => 0,
                'bootstrap' => 0,
                'action' => 0,
                'terminating' => 0,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => 0,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);
    }

    public function test_it_child_commands_do_not_progress_the_modify_execution_stage(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('parent', function (): void {
            Artisan::call('child');

            Cache::get('foo');
        });
        Artisan::command('child', function (): void {
            //
        });

        $run = function () {
            $status = Artisan::handle($input = new StringInput('parent'));

            Artisan::terminate($input, $status);

            return $status;
        };

        $this->assertSame(0, $run());
        $ingest->assertLatestWrite('command:0.cache_events', 1);
        $ingest->assertLatestWrite('cache-event:0.execution_stage', 'action');
    }

    public function test_it_child_commands_do_not_progress_the_modify_execution_stage_when_terminating_event_does_not_exist(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('parent', function (): void {
            Artisan::call('child');

            Cache::get('foo');
        });
        Artisan::command('child', function (): void {
            //
        });
        Compatibility::$terminatingEventExists = false;

        $run = function () {
            $status = Artisan::handle($input = new StringInput('parent'));

            Artisan::terminate($input, $status);

            return $status;
        };

        $this->assertSame(0, $run());
        $ingest->assertLatestWrite('command:0.cache_events', 1);
        $ingest->assertLatestWrite('cache-event:0.execution_stage', 'action');
    }

    #[DataProvider('vendorCommands')]
    public function test_it_ignores_vendor_commands(string $command): void
    {
        $ingest = $this->fakeIngest();

        $status = Artisan::handle($input = new StringInput($command), new NullOutput);
        Artisan::terminate($input, $status);

        $this->assertSame(0, $status);
        $ingest->assertWrittenTimes(0);
    }

    public static function vendorCommands(): iterable
    {
        yield ['help'];
        yield ['inspire'];
        yield ['schedule:finish 123'];
    }
}

class ParentCommand extends Command
{
    public $name = 'parent';

    public function __invoke(): void
    {
        Artisan::call('child');
    }
}
