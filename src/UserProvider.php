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
    private ?Authenticatable $rememberedUser = null;

    public function __construct(
        private AuthManager $auth,
    ) {
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
                return Str::tinyText((string) $this->rememberedUser?->getAuthIdentifier());  // @phpstan-ignore cast.string
            }
        });
    }

    /**
     * @return array{ id: string, name: string, username: string }|null
     */
    public function details(): ?array
    {
        $user = $this->auth->user() ?? $this->rememberedUser;

        if ($user === null) {
            return null;
        }

        return [
            'id' => (string) $user->getAuthIdentifier(), // @phpstan-ignore cast.string
            'name' => (string) ($user->name ?? ''),
            'username' => (string) ($user->email ?? ''),
        ];
    }

    public function remember(Authenticatable $user): void
    {
        $this->rememberedUser = $user;
    }
}
