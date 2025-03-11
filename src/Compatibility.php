<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArgvInput;

use function implode;
use function method_exists;
use function version_compare;

final class Compatibility
{
    public static Application $app;

    public static bool $terminatingEventExists = false;

    public static bool $cacheDurationCapturable = false;

    public static bool $cacheFailuresCapturable = false;

    public static bool $cacheStoreNameCapturable = false;

    public static bool $mailableClassNameCapturable = false;

    public static bool $queueNameCapturable = false;

    public static bool $contextExists = false;

    /**
     * @var array<string, mixed>
     */
    public static array $context = [];

    public static function boot(Application $app): void
    {
        self::$app = $app;
        $version = $app->version();

        /**
         * @see https://github.com/laravel/framework/pull/49730
         * @see https://github.com/laravel/framework/pull/49754
         * @see https://github.com/laravel/framework/pull/49837
         * @see https://github.com/laravel/framework/releases/tag/v11.0.0
         */
        self::$contextExists =
        self::$queueNameCapturable =
        self::$cacheStoreNameCapturable =
            version_compare($version, '11.0.0', '>=');

        /**
         * @see https://github.com/laravel/framework/pull/51560
         * @see https://github.com/laravel/framework/releases/tag/v11.11.0
         */
        self::$cacheFailuresCapturable =
        self::$cacheDurationCapturable =
            version_compare($version, '11.11.0', '>=');

        /**
         * @see https://github.com/laravel/framework/pull/52259
         * @see https://github.com/laravel/framework/releases/tag/v11.18.0
         */
        self::$terminatingEventExists = version_compare($version, '11.18.0', '>=');

        /**
         * @see https://github.com/laravel/framework/pull/53042
         * @see https://github.com/laravel/framework/releases/tag/v11.27.0
         */
        self::$mailableClassNameCapturable = version_compare($version, '11.27.0', '>=');

        if (! self::$contextExists) {
            Queue::createPayloadUsing(static fn ($c, $q, array $payload) => [
                ...$payload,
                'nightwatch' => self::$context,
            ]);

            /** @var Dispatcher */
            $events = $app->make(Dispatcher::class);
            $events->listen(static function (JobProcessing $event) {
                self::$context = $event->job->payload()['nightwatch'] ?? [];
            });
        }
    }

    /**
     * @see https://github.com/symfony/symfony/pull/54347
     * @see https://github.com/symfony/console/releases/tag/v7.1.0-BETA1
     */
    public static function parseCommand(ArgvInput $input): string
    {
        /** @var array<string> */
        $tokens = method_exists($input, 'getRawTokens')
            ? $input->getRawTokens()
            : (new ReflectionProperty(ArgvInput::class, 'tokens'))->getValue($input);

        return implode(' ', $tokens);
    }

    /**
     * @see https://github.com/laravel/framework/pull/49730
     * @see https://github.com/laravel/framework/releases/tag/v11.0.0
     */
    public static function addHiddenContext(string $key, mixed $value): void
    {
        if (! self::$contextExists) {
            self::$context[$key] = $value;

            return;
        }

        /** @var Context */
        $context = self::$app->make(Context::class);

        $context->addHidden($key, $value);
    }

    /**
     * @see https://github.com/laravel/framework/pull/49730
     * @see https://github.com/laravel/framework/releases/tag/v11.0.0
     */
    public static function getHiddenContext(string $key): mixed
    {
        if (! self::$contextExists) {
            return self::$context[$key] ?? null;
        }

        /** @var Context */
        $context = self::$app->make(Context::class);

        return $context->getHidden($key);
    }
}
