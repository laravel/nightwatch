<?php

namespace Tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\Support\ClosureStream;
use Orchestra\Testbench\Attributes\WithEnv;
use RuntimeException;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Tests\FakeIngest;
use Tests\TestCase;

use function dirname;
use function hash;
use function serialize;
use function unserialize;

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

        $this->core->finishExecution();

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

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    public function test_it_hashes_serialized_closures_when_ingesting_fatal_errors(): void
    {
        $ingest = $this->fakeIngest();
        $this->app->setBasePath($base = dirname($this->app->basePath()));
        $this->core->sensor->location->setBasePath($base);
        $code = "function (): void {\n            throw new \\RuntimeException('Whoops!');\n        }";
        $closure = unserialize(serialize(new SerializableClosure(function (): void {
            throw new RuntimeException('Whoops!');
        })));

        try {
            $closure();
        } catch (RuntimeException $e) {
            //
        }

        // A fatal error occurring within a serialized closure is reported
        // against the closure's stream.
        $this->core->report(new FatalError('Out of memory', 0, ['file' => $e->getFile(), 'line' => $e->getLine()], 0));

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.file', ClosureStream::STREAM_PROTO.'://'.hash('xxh128', $code));
        $ingest->assertLatestWrite('exception:0._group', hash('xxh128', "Symfony\Component\ErrorHandler\Error\FatalError,0,".ClosureStream::STREAM_PROTO.'://'.hash('xxh128', $code).",{$e->getLine()}"));
    }

    public function test_it_flushes_buffered_records_before_reporting_a_fatal_error(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->ingest->write(['t' => 'test', 'foo' => 'bar']);

        $this->core->report(new FatalError('Out of memory', 0, ['file' => __FILE__, 'line' => __LINE__], 0));

        // The buffered record is discarded, not sent alongside the fatal error.
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWriteRecordCount(1);
        $ingest->assertLatestWrite('exception:0.message', 'Out of memory');
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_discards_buffered_records_when_a_fatal_error_occurs_while_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['exceptions'] = 0;
        $this->core->dontSample();
        $this->core->ingest->write(['t' => 'test', 'foo' => 'bar']);

        $this->core->report(new FatalError('Out of memory', 0, ['file' => __FILE__, 'line' => __LINE__], 0));

        $this->assertFalse($this->core->sampling());

        // Nothing is sent: the fatal error is dropped by sampling, and the
        // previously buffered record is flushed (discarded) regardless.
        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_writes_an_unhandled_exception_immediately(): void
    {
        $ingest = $this->fakeIngest();

        $this->core->report(new RuntimeException('Whoops!'), handled: false);

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.handled', false);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_buffers_a_handled_exception_instead_of_writing_it_immediately(): void
    {
        $ingest = $this->fakeIngest();

        $this->core->report(new RuntimeException('Whoops!'), handled: true);

        $ingest->assertWrittenTimes(0);
        $this->assertCount(1, $this->core->ingest->buffer);

        // The record was buffered, not lost; it goes out with the next digest.
        $this->core->finishExecution();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.handled', true);
    }

    public function test_it_does_not_flush_other_buffered_records_when_writing_an_unhandled_exception_immediately(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->ingest->write(['t' => 'test', 'foo' => 'bar']);

        $this->core->report(new RuntimeException('Whoops!'), handled: false);

        // The unhandled exception is sent on its own, standalone write.
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWriteRecordCount(1);
        $ingest->assertLatestWrite('exception:0.handled', false);

        // The earlier record is untouched, still sitting in the buffer.
        $this->assertCount(1, $this->core->ingest->buffer);

        $this->core->finishExecution();

        $ingest->assertWrittenTimes(2);
        $ingest->assertLatestWrite('test:0.foo', 'bar');
    }

    public function test_it_buffers_an_unhandled_exception_instead_of_writing_it_immediately_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['exceptions'] = 0;
        $this->core->dontSample();

        $this->core->report(new RuntimeException('Whoops!'), handled: false);

        $this->assertFalse($this->core->sampling());
        $ingest->assertWrittenTimes(0);
        $this->assertCount(1, $this->core->ingest->buffer);

        // Since the execution isn't sampled, finishing it discards the
        // buffer instead of digesting it, so the record is never sent.
        $this->core->finishExecution();

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_does_not_write_when_the_exception_is_not_reported(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->sensor->exceptionSensor = fn () => null;

        $this->core->report(new RuntimeException('Whoops!'), handled: false);

        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_gracefully_handles_exceptions_thrown_while_writing_an_unhandled_exception_immediately(): void
    {
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });
        $this->fakeIngest(fn ($ingest, $streams) => new class($ingest, $streams) extends FakeIngest
        {
            public bool $thrownInWriteNow = false;

            public function writeNow(array $record): void
            {
                $this->thrownInWriteNow = true;

                throw new RuntimeException('Whoops while writing!');
            }
        });

        $this->core->report(new RuntimeException('Original exception'), handled: false);

        $this->assertTrue($this->core->ingest->thrownInWriteNow);
        $this->assertSame(1, $this->core->executionState->exceptions);
        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops while writing!', $exceptions[0]->getMessage());
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    #[WithEnv('NIGHTWATCH_DEPLOY', '82f35860d8c7e59fe4d81a3256a2bb34c998acd9')]
    public function test_it_uses_nightwatch_deploy_env_for_deployments_by_default(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/test', function () {
            return 'OK';
        });

        $this->get('/test')->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.deploy', '82f35860d8c7e59fe4d81a3256a2bb34c998acd9');
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    #[WithEnv('LARAVEL_CLOUD_DEPLOY_UUID', 'cca312d8-2b81-45c1-822b-3ff0bf944c2c')]
    public function test_it_uses_cloud_commit_env_for_deployments_if_available(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/test', function () {
            return 'OK';
        });

        $this->get('/test')->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.deploy', 'cca312d8-2b81-45c1-822b-3ff0bf944c2c');
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    #[WithEnv('FORGE_DEPLOY_COMMIT', '02f35860d8c7e59fe4d81a3256a2bb34c998acd9')]
    public function test_it_uses_forge_commit_env_for_deployments_if_available(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/test', function () {
            return 'OK';
        });

        $this->get('/test')->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.deploy', '02f35860d8c7e59fe4d81a3256a2bb34c998acd9');
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    #[WithEnv('FORGE_DEPLOY_COMMIT', '82f35860d8c7e59fe4d81a3256a2bb34c998acd9')]
    #[WithEnv('NIGHTWATCH_DEPLOY', '12f35860d8c7e59fe4d81a3256a2bb34c998acd9')]
    public function test_it_uses_higher_precedent_deploy_env_for_deployments(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/test', function () {
            return 'OK';
        });

        $this->get('/test')->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.deploy', '12f35860d8c7e59fe4d81a3256a2bb34c998acd9');
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    #[WithEnv('VAPOR_COMMIT_HASH', '02f35860d8c7e59fe4d81a3256a2bb34c998acd9')]
    public function test_it_uses_vapor_commit_hash_for_deployments_if_available(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/test', function () {
            return 'OK';
        });

        $this->get('/test')->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.deploy', '02f35860d8c7e59fe4d81a3256a2bb34c998acd9');
    }
}
