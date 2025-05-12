<?php

namespace Tests;

use Laravel\NightwatchAgent\Contracts\Browser;
use PHPUnit\Framework\Assert;
use React\Promise\PromiseInterface;
use RuntimeException;

use function array_map;
use function array_search;
use function array_shift;
use function gzdecode;
use function json_encode;

class BrowserFake implements Browser
{
    /**
     * @var list<array{0: string, 1: array<string, string>, 2: string }>
     */
    public array $sentRequests = [];

    /**
     * @var array<int, Response>
     */
    public array $processingResponses = [];

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

    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface
    {
        $this->sentRequests[] = [$url, $headers, $body];

        $response = array_shift($this->pendingResponses);

        if ($response === null) {
            throw new RuntimeException('A request was made but there are no more responses: ['.json_encode([
                'url' => $url,
            ], flags: JSON_THROW_ON_ERROR).']');
        }

        $this->processingResponses[] = $response;

        return $response->toPromise()->finally(function () use ($response) {
            $index = array_search($response, $this->processingResponses, true);

            if ($index === false) {
                throw new RuntimeException('Was unable to find the processing response. Something is wrong.');
            }

            unset($this->processingResponses[$index]);
        });
    }

    /**
     * @param  list<Response>  $responses
     */
    public function assertPending(array $responses): self
    {
        Assert::assertEquals($responses, $this->pendingResponses);

        return $this;
    }

    /**
     * @param  list<Request>  $requests
     */
    public function assertSent(array $requests): self
    {
        foreach ($requests as $request) {
            if (($this->headers['content-encoding'] ?? null) === 'gzip') {
                $body = gzdecode($request->body);

                if ($body === false) {
                    throw new RuntimeException('Unable to uncompress request payload.');
                }

                $request->body = $body;
            }
        }

        $actual = array_map(
            function ($request) {
                [$url, $headers, $body] = $request;

                if (($this->headers['content-encoding'] ?? null) === 'gzip') {
                    $body = gzdecode($body);

                    if ($body === false) {
                        throw new RuntimeException('Unable to uncompress request payload.');
                    }
                }

                return new Request($url, $headers, $body);
            },
            $this->sentRequests,
        );

        Assert::assertEquals($requests, $actual);

        return $this;
    }

    /**
     * @param  list<Response>  $responses
     */
    public function assertProcessing(array $responses): self
    {
        Assert::assertEquals($responses, $this->processingResponses);

        return $this;
    }
}
