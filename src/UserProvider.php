<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nightwatch\Types\Str;

use function call_user_func;

/**
 * @internal
 */
final class UserProvider
{
    // TODO we need to reset this state between executions.
    private ?Authenticatable $rememberedUser = null;

    /**
     * @var (callable(): (null|(callable(Authenticatable): array{id: mixed, name?: mixed, username?: mixed})))
     */
    public $userDetailsResolverResolver;

    public function __construct(
        private AuthManager $auth,
        callable $userDetailsResolverResolver,
    ) {
        $this->userDetailsResolverResolver = $userDetailsResolverResolver;
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
     * @return array{ id: mixed, name?: mixed, username?: mixed }|null
     */
    public function details(): ?array
    {
        $user = $this->auth->user() ?? $this->rememberedUser;

        if ($user === null) {
            return null;
        }

        $resolver = call_user_func($this->userDetailsResolverResolver);

        if ($resolver === null) {
            return [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? '',
                'username' => $user->email ?? '',
            ];
        }

        return [
            'id' => $user->getAuthIdentifier(),
            ...$resolver($user),
        ];
    }

    public function remember(Authenticatable $user): void
    {
        $this->rememberedUser = $user;
    }
}
