<?php

namespace Laravel\Nightwatch\Config;

final class HttpIngest
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
        $this->connectionLimit = $config['connection_limit'] ?? 2;
        $this->connectionTimeout = $config['connection_timeout'] ?? 1.0;
        $this->timeout = $config['timeout'] ?? 3.0;
    }
}
