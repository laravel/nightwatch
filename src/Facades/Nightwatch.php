<?php

namespace Laravel\Nightwatch\Facades;

use Illuminate\Support\Facades\Facade;
use Throwable;

use function call_user_func;

/**
 * @method static void user(callable $callback)
 * @method static callable guzzleMiddleware()
 * @method static void sample(float $rate = 1)
 * @method static void dontSample()
 * @method static bool sampling()
 * @method static void captureDefaultVendorCommands(bool $capture = true)
 * @method static array defaultVendorCommands()
 * @method static mixed ignore(callable $callback)
 * @method static void resume()
 * @method static void pause()
 * @method static bool paused()
 * @method static void report(\Throwable $e, bool|null $handled = null)
 * @method static void redactExceptions(callable $callback)
 * @method static void redactCacheEvents(callable $callback)
 * @method static void redactCommands(callable $callback)
 * @method static void redactMail(callable $callback)
 * @method static void redactOutgoingRequests(callable $callback)
 * @method static void redactQueries(callable $callback)
 * @method static void redactRequests(callable $callback)
 * @method static void rejectCacheEvents(callable $callback)
 * @method static void rejectCacheKeys(array $keys)
 * @method static void captureDefaultVendorCacheKeys(bool $capture = true)
 * @method static array defaultVendorCacheKeys()
 * @method static void rejectMail(callable $callback)
 * @method static void rejectNotifications(callable $callback)
 * @method static void rejectOutgoingRequests(callable $callback)
 * @method static void rejectQueries(callable $callback)
 * @method static void rejectQueuedJobs(callable $callback)
 *
 * @see \Laravel\Nightwatch\Core
 */
final class Nightwatch extends Facade
{
    /**
     * @var null|(callable(Throwable): mixed)
     */
    private static $handleUnrecoverableExceptionsUsing;

    /**
     * Get the registered name of the component.
     */
    public static function getFacadeAccessor(): string
    {
        return \Laravel\Nightwatch\Core::class;
    }

    /**
     * @param  (callable(Throwable): mixed)  $callback
     */
    public static function handleUnrecoverableExceptionsUsing(callable $callback): void
    {
        self::$handleUnrecoverableExceptionsUsing = $callback;
    }

    /**
     * @internal
     */
    public static function unrecoverableExceptionOccurred(Throwable $e): void
    {
        if (self::$handleUnrecoverableExceptionsUsing) {
            try {
                call_user_func(self::$handleUnrecoverableExceptionsUsing, $e);
            } catch (Throwable $e) {
                //
            }
        }
    }
}
