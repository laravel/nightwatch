<?php

namespace Tests\Unit;

use Laravel\Nightwatch\GracefulCliOutputExceptionHandler;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

use function app;

class GracefulCliOutputExceptionHandlerTest extends TestCase
{
    public function test_it_gracefully_outputs_errors_for_log_files(): void
    {
        $handler = app(GracefulCliOutputExceptionHandler::class);
        $output = new BufferedOutput;
        $file = __FILE__;

        $line = __LINE__ + 1;
        $handler->renderForConsole($output, new RuntimeException('Whoops!'));

        $this->assertLogMatches(<<<LOG
            {date} {error} An unhandled error occurred\.
            Whoops! in {$file}:{$line}
            To see a full stack trace, pass the `-v` flag when calling the the agent command, e\.g\., `php artisan nightwatch:agent -v`
            LOG, $output->fetch());
    }

    public function test_it_gracefully_outputs_verbose_errors_for_log_files(): void
    {
        $handler = app(GracefulCliOutputExceptionHandler::class);
        $output = new BufferedOutput(verbosity: BufferedOutput::VERBOSITY_VERBOSE);

        $handler->renderForConsole($output, new RuntimeException('Whoops!'));

        $log = $output->fetch();
        $this->assertStringContainsString('Stack trace:', $log);
        $this->assertStringContainsString('#0 ', $log);
        $this->assertStringNotContainsString('To see a full stack trace', $log);
        $this->assertStringNotContainsString('This should not impact the operation of Nightwatch', $log);
    }

    public function test_it_indicates_errors_do_not_impact_nightwatch_on_shutdown(): void
    {
        $handler = app(GracefulCliOutputExceptionHandler::class);
        $output = new BufferedOutput;
        $file = __FILE__;

        $handler->shuttingDown();
        $line = __LINE__ + 1;
        $handler->renderForConsole($output, new RuntimeException('Whoops!'));

        $this->assertLogMatches(<<<LOG
            {date} {warning} An unhandled error occurred while shutting down\.
            Whoops! in {$file}:{$line}
            To see a full stack trace, pass the `-v` flag when calling the the agent command, e\.g\., `php artisan nightwatch:agent -v`
            This should not impact the operation of Nightwatch\.
            LOG, $output->fetch());
    }
}
