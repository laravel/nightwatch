<?php

namespace Laravel\NightwatchAgent;

use Laravel\NightwatchAgent\Contracts\Browser as BrowserContract;
use React\Http\Browser as ReactBrowser;
use React\Promise\PromiseInterface;

class Browser implements BrowserContract
{
    public function __construct(
        private ReactBrowser $browser,
    ) {
        //
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface
    {
        return $this->browser->post($url, [
            ...$headers,
        ], $body);
    }
}
