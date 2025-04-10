<?php

namespace Tests;

use Laravel\NightwatchAgent\Contracts\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;

use function array_search;
use function array_values;
use function json_encode;

class BrowserFake implements Browser
{
    /**
     * @var list<array{0: string, 1: array<string, string>, 2: string }>
     */
    public array $sentRequests = [];

    public ?float $connectionTimeout = null;

    public ?float $timeout = null;

    public ?string $baseUrl = null;

    /**
     * @var array<string, string>|null
     */
    public ?array $headers = null;

    /**
     * @param  array<int, Response>  $pendingResponses
     */
    public function __construct(
        public array $pendingResponses = [],
    ) {
        //
    }

    public function post(string $url, array $headers, string $body): PromiseInterface
    {
        $this->sentRequests[] = [$url, $headers, $body];

        $response = array_values($this->pendingResponses)[0] ?? null;

        if ($response === null) {
            throw new RuntimeException('A request was made but there are no more responses: ['.json_encode([
                'url' => $url,
            ], flags: JSON_THROW_ON_ERROR).']');
        }

        return $response->toPromise()->finally(function () use ($response) {
            $index = array_search($response, $this->pendingResponses, true);

            if ($index === false) {
                throw new RuntimeException('Was unable to find the response in the pending responses. Something is wrong.');
            }

            unset($this->pendingResponses[$index]);
        });
    }
}
