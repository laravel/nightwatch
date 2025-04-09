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
        array $headers = [],
        ?string $baseUrl = null,
    ): BrowserContract {
        $connector = new Connector(['timeout' => $connectionTimeout]);

        $browser = (new ReactBrowser($connector))
            ->withTimeout($timeout)
            ->withBase($baseUrl);

        foreach ($headers as $key => $value) {
            $browser = $browser->withHeader($key, $value);
        }

        return new NightwatchBrowser($browser, [
            'user-agent' => fn () => 'NightwatchAgent/'.$this->packageVersion->get(),
        ]);
    }
}
