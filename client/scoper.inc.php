<?php

return [
    'prefix' => 'NightwatchClient_kden27khxA4QoEfj',
    'patchers' => [
        // There are situations when this file is manually required twice by
        // Box. This has been seen mostly during installation scripts when the
        // agent is not running and an exception is thrown. We ensure the file
        // is only ever required once to fix this issue manually.
        static function (string $filePath, string $prefix, string $content): string {
            if ($filePath === 'vendor/composer/InstalledVersions.php') {
                $content = str_replace('class InstalledVersions', "\nif (class_exists(InstalledVersions::class, autoload: false)) { return; }\n\nclass InstalledVersions", $content);
            }

            return $content;
        },
    ],
];
