<?php

namespace Tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Attributes\WithEnv;
use RuntimeException;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Tests\FakeIngest;
use Tests\TestCase;

use function dirname;
use function hash;

class CoreTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions_thrown_while_ingesting(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $this->fakeIngest(fn ($ingest, $streams) => new class($ingest, $streams) extends FakeIngest
        {
            public bool $thrownInDigest = false;

            public function digest(): void
            {
                $this->thrownInDigest = true;

                throw new RuntimeException('Whoops!');
            }
        });

        $this->core->digest();

        $this->assertTrue($this->core->ingest->thrownInDigest);
        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    public function test_it_ingests_fatal_errors_immediately(): void
    {
        $ingest = $this->fakeIngest();
        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
        $this->setPhpVersion('8.4.1');
        $this->setLaravelVersion('11.33.0');
        $this->app->setBasePath($base = dirname($this->app->basePath()));
        $this->core->sensor->location->setBasePath($base);
        $this->core->executionState->executionPreview = 'GET /fatal';
        $this->core->stage(ExecutionStage::Action);
        Auth::setUser($user = User::factory()->create());

        $this->core->report(new FatalError('Out of memory', 0, ['file' => __FILE__, 'line' => $line = __LINE__], 0));

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:*', [
            [
                'v' => 3,
                't' => 'exception',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', "Symfony\Component\ErrorHandler\Error\FatalError,0,tests/Unit/CoreTest.php,{$line}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '',
                'execution_preview' => 'GET /fatal',
                'execution_stage' => 'action',
                'user' => (string) $user->id,
                'class' => 'Symfony\Component\ErrorHandler\Error\FatalError',
                'file' => 'tests/Unit/CoreTest.php',
                'line' => $line,
                'message' => 'Out of memory',
                'code' => '0',
                'trace' => '',
                'handled' => false,
                'php_version' => '8.4.1',
                'laravel_version' => '11.33.0',
            ],
        ]);
    }
}
