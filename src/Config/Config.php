<?php

namespace Laravel\Nightwatch\Config;

use SensitiveParameter;

class Config
{
    public bool $disabled;

    public string $envId;

    public string $envSecret;

    public string $deployment;

    public string $server;

    public string $localIngest;

    public string $remoteIngest;

    public int $bufferThreshold;

    public string $errorLogChannel;

    public SocketIngest $socketIngest;

    public HttpIngest $httpIngest;

    public LogIngest $logIngest;

    /**
     * @param  array{
     *      disabled?: bool,
     *      env_id?: string,
     *      env_secret?: string,
     *      deployment?: string,
     *      server?: string,
     *      local_ingest?: string,
     *      remote_ingest?: string,
     *      buffer_threshold?: int,
     *      error_log_channel?: string,
     *      ingests: array{
     *          socket?: array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float },
     *          http?: array{ uri?: string, connection_limit?: int, connection_timeout?: float, timeout?: float },
     *          log?: array{ channel?: string },
     *      }
     * }  $config
     */
    public function __construct(
        #[\SensitiveParameter] array $config
    ) {
        $this->disabled = $config['disabled'] ?? false;
        $this->envId = $config['env_id'] ?? '';
        $this->envSecret = $config['env_secret'] ?? '';
        $this->deployment = $config['deployment'] ?? '';
        $this->server = $config['server'] ?? '';
        $this->localIngest = $config['local_ingest'] ?? 'socket';
        $this->remoteIngest = $config['remote_ingest'] ?? 'http';
        $this->bufferThreshold = $config['buffer_threshold'] ?? 1_000_000;
        $this->errorLogChannel = $config['error_log_channel'] ?? 'stderr';

        $this->socketIngest = new SocketIngest($config['ingests']['socket'] ?? []);
        $this->httpIngest = new HttpIngest($config['ingests']['http'] ?? []);
        $this->logIngest = new LogIngest($config['ingests']['log'] ?? []);
    }
}
