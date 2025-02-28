<?php

namespace Laravel\Nightwatch;

use Illuminate\Foundation\Application;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArgvInput;

use function implode;
use function method_exists;
use function version_compare;

class Supports
{
    public static bool $terminatingEvent = true;

    public static bool $cacheDuration = true;

    public static bool $cacheStoreName = true;

    public static function boot(): void
    {
        /**
         * @see https://github.com/laravel/framework/pull/52259
         * @see https://github.com/laravel/framework/releases/tag/v11.18.0
         */
        self::$terminatingEvent = version_compare(Application::VERSION, '11.18.0', '>=');

        /**
         * @see https://github.com/laravel/framework/pull/51560
         * @see https://github.com/laravel/framework/releases/tag/v11.11.0
         */
        self::$cacheDuration = version_compare(Application::VERSION, '11.11.0', '>=');

        /**
         * @see https://github.com/laravel/framework/pull/49754
         * @see https://github.com/laravel/framework/releases/tag/v11.0.0
         */
        self::$cacheStoreName = version_compare(Application::VERSION, '11.0.0', '>=');
    }

    /**
     * @see https://github.com/symfony/symfony/pull/54347
     * @see https://github.com/symfony/console/releases/tag/v7.1.0-BETA1
     */
    public static function parseCommand(ArgvInput $input): string
    {
        if (method_exists($input, 'getRawTokens')) {
            return $input->getRawTokens();
        }

        return implode(' ', (new ReflectionProperty(ArgvInput::class, 'tokens'))->getValue($input));
    }
}
