<?php

namespace Laravel\Nightwatch;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nightwatch\Types\Str;
use Throwable;

use function call_user_func;

/**
 * @internal
 */
final class UserProvider
{
    private ?Authenticatable $rememberedUser = null;

    /**
     * @var (callable(): (null|(callable(Authenticatable): array{id: mixed, name?: mixed, username?: mixed})))
     */
    public $userDetailsResolverResolver;

    /**
     * @var (callable(callable(AuthManager): mixed): mixed)
     */
    private $withAuth;

    /**
     * @var (callable(): (callable(Throwable, bool): void))
     */
    private $reportResolver;

    private bool $alreadyReportedResolvingUserIdException = false;

    public function __construct(
        callable $withAuth,
        callable $userDetailsResolverResolver,
        callable $reportResolver,
    ) {
        $this->withAuth = $withAuth;
        $this->userDetailsResolverResolver = $userDetailsResolverResolver;
        $this->reportResolver = $reportResolver;
    }

    /**
     * @return string|LazyValue<string>
     */
    public function id(): LazyValue|string
    {
        if (! $this->withAuth(static fn ($auth) => $auth->hasResolvedGuards())) {
            return $this->lazyUserId();
        }

        if ($this->withAuth(static fn ($auth) => $auth->hasUser())) {
            return $this->currentUserId();
        }

        if ($this->rememberedUser) {
            return $this->rememberedUserId();
        }

        return $this->lazyUserId();
    }

    /**
     * @return LazyValue<string>
     */
    private function lazyUserId(): LazyValue
    {
        return new LazyValue(function () {
            if (! $this->withAuth(static fn ($auth) => $auth->hasResolvedGuards())) {
                return '';
            }

            if ($this->withAuth(static fn ($auth) => $auth->hasUser())) {
                return $this->currentUserId();
            }

            if ($this->rememberedUser) {
                return $this->rememberedUserId();
            }

            return '';
        });
    }

    public function resolvedUserId(): string
    {
        return $this->withAuth(function ($auth) {
            if (! $auth->hasResolvedGuards()) {
                return '';
            }

            if ($auth->hasUser()) {
                return $this->currentUserId();
            }

            if ($this->rememberedUser) {
                return $this->rememberedUserId();
            }

            return '';
        });
    }

    private function currentUserId(): string
    {
        try {
            return Str::tinyText((string) $this->withAuth(static fn ($auth) => $auth->id()));
        } catch (Throwable $e) {
            $this->reportResolvingUserIdException($e);

            return '';
        }
    }

    private function rememberedUserId(): string
    {
        try {
            return Str::tinyText((string) $this->rememberedUser?->getAuthIdentifier());  // @phpstan-ignore cast.string
        } catch (Throwable $e) {
            $this->reportResolvingUserIdException($e);

            return '';
        }
    }

    /**
     * @return array{ id: mixed, name?: mixed, username?: mixed }|null
     */
    public function details(): ?array
    {
        $user = $this->withAuth(fn ($auth) => $auth->hasResolvedGuards()
            ? $auth->user() ?? $this->rememberedUser
            : $this->rememberedUser);

        if ($user === null) {
            return null;
        }

        try {
            $id = $user->getAuthIdentifier();
        } catch (Throwable $e) {
            $this->reportResolvingUserIdException($e);

            return null;
        }

        $resolver = call_user_func($this->userDetailsResolverResolver);

        if ($resolver === null) {
            return [
                'id' => $id,
                'name' => $user->name ?? '',
                'username' => $user->email ?? '',
            ];
        }

        return [
            'id' => $id,
            ...$resolver($user),
        ];
    }

    public function remember(Authenticatable $user): void
    {
        $this->rememberedUser = $user;
    }

    public function flush(): void
    {
        $this->rememberedUser = null;
        $this->alreadyReportedResolvingUserIdException = false;
    }

    private function reportResolvingUserIdException(Throwable $e): void
    {
        if ($this->alreadyReportedResolvingUserIdException) {
            return;
        }

        $this->alreadyReportedResolvingUserIdException = true;

        $report = call_user_func($this->reportResolver);

        $report($e, true);
    }

    /**
     * @template TValue
     *
     * @param  callable(AuthManager): TValue  $callback
     * @return TValue
     */
    private function withAuth(callable $callback): mixed
    {
        return ($this->withAuth)($callback);
    }
}
