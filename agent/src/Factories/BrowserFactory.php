<?php

namespace Laravel\NightwatchAgent\Factories;

use Laravel\NightwatchAgent\Browser as NightwatchBrowser;
use Laravel\NightwatchAgent\Contracts\Browser as BrowserContract;
use Laravel\NightwatchAgent\PackageVersionRepository;
use React\Http\Browser as ReactBrowser;
use React\Socket\Connector;

class BrowserFactory
{
    public function __construct(
        private PackageVersionRepository $packageVersion,
    ) {
        //
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function __invoke(
        float $connectionTimeout,
        float $timeout,
        string $server,
        array $headers = [],
        string $baseUrl = '',
        bool $debug = false,
    ): BrowserContract {
        $connector = new Connector(['timeout' => $connectionTimeout]);

        $browser = (new ReactBrowser($connector))
            ->withTimeout($timeout)
            ->withHeader('nightwatch-server', $server);

        if ($baseUrl) {
            $browser = $browser->withBase($baseUrl);
        }

        foreach ($headers as $key => $value) {
            $browser = $browser->withHeader($key, $value);
        }

        if ($debug) {
            $browser = $browser->withHeader('nightwatch-debug', '1');
        }

        return new NightwatchBrowser($browser, [
            'user-agent' => fn () => 'NightwatchAgent/'.$this->packageVersion->get(),
        ]);
    }
}
