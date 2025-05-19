<?php

namespace Tests;

use Evenement\EventEmitter;
use PHPUnit\Framework\Assert;
use React\Socket\ServerInterface;
use RuntimeException;

use function is_string;
use function json_encode;
use function strlen;

class TcpServerFake extends EventEmitter implements ServerInterface
{
    /**
     * @var list<Connection>
     */
    public array $connections = [];

    public bool $closed = false;

    /**
     * @param  string|list<array<string, mixed>>  $records
     */
    public function pendingConnection(array|string $records, ?string $signature = null): PendingConnection
    {
        if (is_string($records)) {
            return new PendingConnection($this, $records);
        }

        $records = json_encode($records, flags: JSON_THROW_ON_ERROR);

        $records = (strlen($records) + 8).':'.TestCase::agentSignature().':'.$records;

        return new PendingConnection($this, $records);
    }

    public function getAddress()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function pause()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function resume()
    {
        throw new RuntimeException(__FUNCTION__);
    }

    public function close()
    {
        $this->closed = true;
    }

    /**
     * @param  list<Connection>  $connections
     */
    public function assertHandled(array $connections): self
    {
        Assert::assertEquals($connections, $this->connections);

        return $this;
    }

    public function assertOpen(): self
    {
        Assert::assertFalse($this->closed);

        return $this;
    }

    public function assertClosed(): self
    {
        Assert::assertTrue($this->closed);

        return $this;
    }
}
