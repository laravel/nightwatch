<?php

namespace Laravel\Nightwatch;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;

use function is_array;
use function json_decode;

final class AuthTokenRepository
{
    public function __construct(
        private Browser $browser,
    ) {
        //
    }

    /**
     * @return PromiseInterface<AuthToken>
     */
    public function refresh(): PromiseInterface
    {
        return $this->browser->post('')->then(static function (ResponseInterface $response) {
            $data = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($data) || ! isset($data['token'], $data['expires_in']) || ! $data['token'] || ! $data['expires_in']) {
                throw new RuntimeException("Invalid authentication response: [{$response->getBody()->getContents()}]");
            }

            return new AuthToken($data['token'], $data['expires_in']);
        });
    }
}
