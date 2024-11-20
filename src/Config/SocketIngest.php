<?php

namespace Laravel\Nightwatch\Config;

class SocketIngest
{
    public string $uri;

    public int $connectionLimit;

    public float $connectionTimeout;

    public float $timeout;

    /**
     * @param  array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float }  $config
     */
    public function __construct(array $config)
    {
        $this->uri = $config['uri'] ?? '';
        $this->connectionLimit = $config['connection_limit'] ?? 20;
        $this->connectionTimeout = $config['connection_timeout'] ?? 0.5;
        $this->timeout = $config['timeout'] ?? 0.5;
    }
}
