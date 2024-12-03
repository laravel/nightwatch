<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nightwatch\Types\Str;

/**
 * @internal
 */
final class UserProvider
{
    // TODO we need to reset this state between executions.
    private string $rememberedUserId = '';

    public function __construct(private AuthManager $auth)
    {
        //
    }

    /**
     * @return string|LazyValue<string>
     */
    public function id(): LazyValue|string
    {
        if ($this->auth->hasUser()) {
            return Str::tinyText((string) $this->auth->id());
        }

        return new LazyValue(function () {
            if ($this->auth->hasUser()) {
                return Str::tinyText((string) $this->auth->id());
            } else {
                return $this->rememberedUserId;
            }
        });
    }

    public function remember(Authenticatable $user): void
    {
        $this->rememberedUserId = Str::tinyText((string) $user->getAuthIdentifier()); // @phpstan-ignore cast.string
    }
}
