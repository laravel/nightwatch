<?php

namespace Laravel\NightwatchAgent\Factories;

use Laravel\NightwatchAgent\Browser as NightwatchBrowser;
use Laravel\NightwatchAgent\Contracts\Browser as BrowserContract;
use React\Http\Browser as ReactBrowser;
use React\Socket\Connector;

class BrowserFactory
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __invoke(
        float $connectionTimeout,
        float $timeout,
        array $headers = [],
        ?string $baseUrl = null,
    ): BrowserContract {
        $connector = new Connector(['timeout' => $connectionTimeout]);

        $browser = (new ReactBrowser($connector))
            ->withTimeout($timeout)
            ->withBase($baseUrl)
            ->withoutHeader('User-Agent');

        foreach ($headers as $key => $value) {
            $browser = $browser->withHeader($key, $value);
        }

        return new NightwatchBrowser($browser);
    }
}
