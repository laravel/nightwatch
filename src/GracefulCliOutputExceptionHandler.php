<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

use function date;
use function Nightwatch\fwrite_all;

/**
 * @internal
 */
final class GracefulCliOutputExceptionHandler implements ExceptionHandler
{
    private bool $shuttingDown = false;

    public function __construct(
        private ExceptionHandler $handler,
    ) {
        //
    }

    public function shuttingDown(): void
    {
        $this->shuttingDown = true;
    }

    /**
     * @return void
     */
    public function report(Throwable $e)
    {
        $this->handler->report($e);
    }

    /**
     * @return bool
     */
    public function shouldReport(Throwable $e)
    {
        return $this->handler->shouldReport($e);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        return $this->handler->render($request, $e);
    }

    /**
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     */
    public function renderForConsole($output, Throwable $e): void
    {
        $output = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $writeLine = $output instanceof StreamOutput
            ? static fn (string $message) => fwrite_all($output->getStream(), $message.PHP_EOL)
            : static fn (string $message) => $output->write($message.PHP_EOL);

        if ($this->shuttingDown) {
            $writeLine(date('Y-m-d H:i:s').' [WARNING] An unhandled error occurred while shutting down.');
        } else {
            $writeLine(date('Y-m-d H:i:s').' [ERROR] An unhandled error occurred.');
        }

        $writeLine("{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

        $writeLine($output->isVerbose()
            ? <<<MESSAGE
                        Stack trace:
                        {$e->getTraceAsString()}
                        MESSAGE
            : 'To see a full stack trace, pass the `-v` flag when calling the the agent command, e.g., `php artisan nightwatch:agent -v`');

        if ($this->shuttingDown) {
            $writeLine('This should not impact the operation of Nightwatch.');
        }
    }

    public function __get(string $name): mixed
    {
        return $this->handler->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->handler->{$name} = $value;
    }

    /**
     * @param  array<mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->handler->{$name}(...$arguments);
    }
}
