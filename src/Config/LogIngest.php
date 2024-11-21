<?php

namespace Laravel\Nightwatch\Config;

final class LogIngest
{
    public string $channel;

    /**
     * @param  array{ channel?: string }  $config
     */
    public function __construct(array $config)
    {
        $this->channel = $config['channel'] ?? 'single';
    }
}
