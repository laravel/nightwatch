<?php

namespace Laravel\NightwatchClient;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use Throwable;

use function React\Async\await;

class Ingest
{
    public function __construct(
        private ConnectorInterface $connector,
        private string $transmitTo,
    ) {
        //
    }

    public function __invoke(string $payload): string
    {
        return await(match ($payload) {
            'PING' => $this->ping(),
            default => $this->ingest($payload),
        });
    }

    /**
     * @return PromiseInterface<string>
     */
    private function ingest(string $payload): PromiseInterface
    {
        return $this->connect()->then(static function (ConnectionInterface $connection) use ($payload) {
            $connection->end($payload);

            return '';
        });
    }

    /**
     * @return PromiseInterface<string>
     */
    private function ping(): PromiseInterface
    {
        return $this->connect()->then(static function (ConnectionInterface $connection) {
            $output = '';
            /** @var Deferred<string> $deferred */
            $deferred = new Deferred;

            $connection->on('data', static function (string $data) use (&$output): void {
                $output .= $data;
            });

            $connection->on('end', static function () use (&$output, $deferred) {
                $deferred->resolve($output);
            });

            $connection->on('close', static function () use (&$output, $deferred) {
                $deferred->resolve($output);
            });

            $connection->on('error', static function (Throwable $e) use ($deferred) {
                $deferred->reject($e);
            });

            $connection->write('PING');

            return $deferred->promise();
        });
    }

    /**
     * @return PromiseInterface<ConnectionInterface>
     */
    private function connect(): PromiseInterface
    {
        return $this->connector->connect($this->transmitTo);
    }
}
