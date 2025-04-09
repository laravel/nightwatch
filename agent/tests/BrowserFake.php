<?php

namespace Tests;

use Exception;
use Laravel\NightwatchAgent\Contracts\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;

use function array_shift;
use function is_array;
use function json_encode;
use function React\Promise\reject;

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
     * @param  list<Response|array{0: class-string<Exception>, 1: string}>  $pendingResponses
     */
    public function __construct(
        public array $pendingResponses = [],
    ) {
        //
    }

    public function post(string $url, array $headers, string $body): PromiseInterface
    {
        $this->sentRequests[] = [$url, $headers, $body];

        $response = array_shift($this->pendingResponses);

        if ($response === null) {
            throw new RuntimeException('A request was made but there are no more responses: ['.json_encode([
                'url' => $url,
            ], flags: JSON_THROW_ON_ERROR).']');
        }

        if (is_array($response)) {
            return reject(new $response[0]($response[1]));
        }

        return $response->toPromise();
    }
}
