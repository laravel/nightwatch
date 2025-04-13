<?php

namespace Laravel\NightwatchAgent;

use Laravel\NightwatchAgent\Contracts\Browser as BrowserContract;
use React\Http\Browser as ReactBrowser;
use React\Promise\PromiseInterface;

class Browser implements BrowserContract
{
    /**
     * @param  array<string, (callable(): string)>  $lazyHeaders
     */
    public function __construct(
        private ReactBrowser $browser,
        private array $lazyHeaders,
    ) {
        //
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface
    {
        return $this->browser->post($url, [
            $this->headers(),
            ...$headers,
        ], $body);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];

        foreach ($this->lazyHeaders as $key => $value) {
            $headers[$key] = $value();
        }

        return $headers;
    }
}
