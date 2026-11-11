<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Context;
use Symfony\Component\Console\Input\ArgvInput;

use function function_exists;
use function implode;
use function laravel_cloud;
use function value;
use function version_compare;

/**
 * @internal
 */
final class Compatibility
{
    public static bool $firesFinishedAndFailedEventsForScheduledConsoleCommands = false;

    public static bool $queryConnectionTypeCapturable = false;

    public static bool $isLaravelCloud = false;

    public static function boot(Application $app): void
    {
        $version = $app->version();

        /**
         * @see https://github.com/laravel/framework/pull/55572
         * @see https://github.com/laravel/framework/releases/tag/v12.11.0
         * @see https://github.com/laravel/framework/releases/tag/v12.11.1
         * @see https://github.com/laravel/framework/pull/55624
         * @see https://github.com/laravel/framework/releases/tag/v12.18.0
         */
        self::$firesFinishedAndFailedEventsForScheduledConsoleCommands = version_compare($version, '12.11.0', '=') || version_compare($version, '12.18.0', '>=');

        /**
         * @see https://github.com/laravel/framework/pull/58156
         * @see https://github.com/laravel/framework/releases/tag/v12.45.0
         */
        self::$queryConnectionTypeCapturable = version_compare($version, '12.45.0', '>=');

        self::$isLaravelCloud = function_exists('laravel_cloud')
            ? laravel_cloud()
            : (($_ENV['LARAVEL_CLOUD'] ?? false) === '1' || ($_SERVER['LARAVEL_CLOUD'] ?? false) === '1');
    }

    public static function parseCommand(ArgvInput $input): string
    {
        return implode(' ', $input->getRawTokens());
    }

    public static function addSamplingToContext(bool $sample): void
    {
        self::addHiddenContext('nightwatch_should_sample', $sample);
    }

    public static function removeSamplingFromContext(): void
    {
        self::addHiddenContext('nightwatch_should_sample', null);
    }

    /**
     * @template T of bool|null
     *
     * @param  T  $default
     * @return (T is bool ? bool : bool|null)
     */
    public static function getSamplingFromContext(?bool $default = true)
    {
        $context = self::getHiddenContext('nightwatch_should_sample', $default);

        if ($context === null) {
            return null;
        }

        return (bool) $context;
    }

    public static function addTraceIdToContext(string $trace): void
    {
        self::addHiddenContext('nightwatch_trace_id', $trace);
    }

    public static function getTraceIdFromContext(mixed $default = null): mixed
    {
        return self::getHiddenContext('nightwatch_trace_id', $default);
    }

    public static function addUserIdToContext(string $id): void
    {
        self::addHiddenContext('nightwatch_user_id', $id);
    }

    public static function getUserIdFromContext(): string
    {
        return (string) self::getHiddenContext('nightwatch_user_id'); // @phpstan-ignore cast.string
    }

    /**
     * @param  'nightwatch_trace_id'|'nightwatch_should_sample'|'nightwatch_user_id'  $key
     */
    private static function addHiddenContext(string $key, mixed $value): void
    {
        Context::addHidden($key, $value);
    }

    /**
     * @param  'nightwatch_trace_id'|'nightwatch_should_sample'|'nightwatch_user_id'  $key
     */
    private static function getHiddenContext(string $key, mixed $default = null): mixed
    {
        return Context::getHidden($key) ?? value($default);
    }
}
