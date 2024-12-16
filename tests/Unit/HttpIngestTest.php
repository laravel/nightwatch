<?php

use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Ingests\Remote\HttpClient;
use Laravel\Nightwatch\Ingests\Remote\HttpIngest;
use React\Http\Message\Response;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

it('limits the number of concurrent requests', function () {
    $deferred = new Deferred;
    $ingest = new HttpIngest(new class($deferred->promise()) extends HttpClient
    {
        public function __construct(private PromiseInterface $promise) {}

        public function send(string $payload): PromiseInterface
        {
            return $this->promise;
        }
    }, new Clock(0), 5);

    $ingest->write('[]');
    $ingest->write('[]');
    $ingest->write('[]');
    $ingest->write('[]');
    $ingest->write('[]');
    $exception = null;
    $ingest->write('[]')->catch(function (Throwable $e) use (&$exception) {
        $exception = $e;
    });
    expect($exception::class)->toBe(RuntimeException::class);
    expect($exception->getMessage())->toBe('Exceeded concurrent request limit [5].');
});

it('tracks resolved requests when considering connection limit', function () {
    $deferredPromises = [
        new Deferred,
        new Deferred,
        new Deferred,
        new Deferred,
        new Deferred,
        new Deferred,
    ];
    $ingest = new HttpIngest(new class($deferredPromises) extends HttpClient
    {
        public function __construct(private array $deferredPromises) {}

        public function send(string $payload): PromiseInterface
        {
            return array_shift($this->deferredPromises)->promise();
        }
    }, new Clock(0), 2);

    $response = new Response;
    $ingest->write('[]');
    $deferredPromises[0]->resolve($response);
    $ingest->write('[]');
    $deferredPromises[1]->resolve($response);
    $ingest->write('[]');
    $ingest->write('[]');
    $deferredPromises[2]->resolve($response);
    $deferredPromises[3]->resolve($response);
    $ingest->write('[]');
    $ingest->write('[]');
    $exception = null;
    $ingest->write('[]')->catch(function (Throwable $e) use (&$exception) {
        $exception = $e;
    });
    expect($exception::class)->toBe(RuntimeException::class);
    expect($exception->getMessage())->toBe('Exceeded concurrent request limit [2].');
});
