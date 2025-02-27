<?php

namespace Laravel\NightwatchAgent;

use Throwable;

use function file_get_contents;
use function trim;

class PackageVersionRepository
{
    public function __construct(
        private string $path,
    ) {
        //
    }

    public function get(): string
    {
        try {
            $version = file_get_contents($this->path);

            if ($version === false) {
                return '';
            }

            return trim($version);
        } catch (Throwable $e) {
            return '';
        }
    }
}
