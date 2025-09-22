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
     * @var array{id: mixed, name?: mixed, username?: mixed}
     */
    private ?array $resolvedDetails;

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
        return $this->withAuth(function ($auth) {
            if (! $auth->hasResolvedGuards()) {
                return $this->lazyUserId();
            }

            if ($auth->hasUser()) {
                return $this->userId($auth->user()); // @phpstan-ignore argument.type
            }

            if ($this->rememberedUser) {
                return $this->userId($this->rememberedUser);
            }

            return $this->lazyUserId();
        });
    }

    /**
     * @return LazyValue<string>
     */
    private function lazyUserId(): LazyValue
    {
        return new LazyValue(function () {
            return $this->resolvedUserId();
        });
    }

    public function resolvedUserId(): string
    {
        return $this->withAuth(function ($auth) {
            if (! $auth->hasResolvedGuards()) {
                return Compatibility::getUserIdFromContext();
            }

            if ($auth->hasUser()) {
                return $this->userId($auth->user()); // @phpstan-ignore argument.type
            }

            if ($this->rememberedUser) {
                return $this->userId($this->rememberedUser);
            }

            return Compatibility::getUserIdFromContext();
        });
    }

    private function userId(Authenticatable $user): string
    {
        try {
            return Str::tinyText((string) ($this->resolvedDetails($user)['id'] ?? '')); // @phpstan-ignore cast.string
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

        return $this->resolvedDetails($user);
    }

    /**
     * @return array{ id: mixed, name?: mixed, username?: mixed }|null
     */
    private function resolvedDetails(?Authenticatable $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (isset($this->resolvedDetails)) {
            return $this->resolvedDetails;
        }

        try {
            $id = $user->getAuthIdentifier();
        } catch (Throwable $e) {
            $this->reportResolvingUserIdException($e);

            return null;
        }

        $resolver = call_user_func($this->userDetailsResolverResolver);

        if ($resolver === null) {
            return $this->resolvedDetails = [
                'id' => $id,
                'name' => $user->name ?? '',
                'username' => $user->email ?? '',
            ];
        }

        return $this->resolvedDetails = [
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
        $this->resolvedDetails = null;
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
