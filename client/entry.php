<?php

namespace Laravel\NightwatchClient;

use function call_user_func;
use function class_exists;

return call_user_func(static function () {
    if (! class_exists(Cache::class, autoload: false)) {
        class Cache
        {
            /**
             * @var IngestFactory
             */
            public static $ingestFactory;
        }

        /** @var IngestFactory $ingestFactory */
        require __DIR__.'/build/client.phar';

        Cache::$ingestFactory = $ingestFactory;
    }

    return Cache::$ingestFactory;
});
