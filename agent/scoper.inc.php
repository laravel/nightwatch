<?php

return [
    'prefix' => 'NightwatchAgent_kden27khxA4QoEfj',
    'patchers' => [
        // There are situations when this file is manually required twice by Box causing a fatal PHP error.
        static function (string $filePath, string $prefix, string $content): string {
            if ($filePath === 'vendor/composer/InstalledVersions.php') {
                $content = str_replace('class InstalledVersions', "\nif (! class_exists(InstalledVersions::class, autoload: false)) {\nclass InstalledVersions", $content).'}'.PHP_EOL;
            }

            return $content;
        },
    ],
];
