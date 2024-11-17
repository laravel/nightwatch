<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\ViewException;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

use function array_key_exists;
use function Illuminate\Filesystem\join_paths;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * @internal
 */
final class Location
{
    private string $basePath;

    private string $artisanPath;

    private string $publicIndexPath;

    private string $vendorPath;

    private string $nightwatchPath;

    private string $frameworkPath;

    public function __construct(
        string $basePath,
        string $publicPath,
    ) {
        $this->basePath = $basePath.DIRECTORY_SEPARATOR;
        $this->artisanPath = join_paths($basePath, 'artisan');
        $this->vendorPath = join_paths($basePath, 'vendor');
        $this->nightwatchPath = join_paths($this->vendorPath, 'laravel', 'nightwatch');
        $this->frameworkPath = join_paths($this->vendorPath, 'laravel', 'framework');
        $this->publicIndexPath = join_paths($publicPath, 'index.php');
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     * @return array{ 0: string|null, 1: int|null }
     */
    public function forQueryTrace(array $trace): array
    {
        /** @var string|null $nonInternalFile */
        $nonInternalFile = null;
        /** @var int|null $nonInternalLine */
        $nonInternalLine = null;

        foreach ($trace as $index => $frame) {
            // The first frame will always be the frame where we capture the
            // stack which is currently in our service provider. Instead of
            // using `array_shift` and creating an entirely new array we will
            // just skip the first frame as we never want to consider it.
            if ($index === 0 || ! isset($frame['file'])) {
                continue;
            }

            // First, we want to find the location in the end-user application:
            if (! $this->isVendorFile($frame['file'])) {
                return [
                    $this->normalizeFile($frame['file']),
                    $frame['line'] ?? null,
                ];
            }

            // We only want to track the first non-internal file we come across,
            // so once we have that we can ignore any others. Otherwise, we will
            // capture the first non-internal file and line as the fallback:
            if ($nonInternalFile === null && ! $this->isInternalFile($frame['file'])) {
                $nonInternalFile = $frame['file'];
                $nonInternalLine = $frame['line'] ?? null;
            }
        }

        if ($nonInternalFile !== null) {
            $nonInternalFile = $this->normalizeFile($nonInternalFile);
        }

        return [
            $nonInternalFile,
            $nonInternalLine,
        ];
    }

    /**
     * @return array{ 0: string, 1: int|null }
     */
    public function forException(Throwable $e): array
    {
        $location = match (true) {
            $e instanceof ViewException => $this->fromViewException($e),
            $e instanceof IgnitionViewException => $this->fromSpatieViewException($e),
            default => null,
        };

        if ($location !== null) {
            return $location;
        }

        return $this->fromThrowable($e);
    }

    /**
     * @return null|array{ 0: string, 1: null }
     */
    private function fromViewException(ViewException $e): ?array
    {
        preg_match('/\(View: (?P<path>.*?)\)$/', $e->getMessage(), $matches);

        if (! array_key_exists('path', $matches)) {
            return null;
        }

        return [
            $this->normalizeFile($matches['path']),
            null,
        ];
    }

    /**
     * @return array{ 0: string, 1: int }
     */
    private function fromSpatieViewException(IgnitionViewException $e): array
    {
        return [
            $this->normalizeFile($e->getFile()),
            $e->getLine(),
        ];
    }

    /**
     * @return array{ 0: string, 1: int|null }
     */
    private function fromThrowable(Throwable $e): array
    {
        if (! $this->isVendorFile($e->getFile())) {
            return [
                $this->normalizeFile($e->getFile()),
                $e->getLine(),
            ];
        }

        $location = $this->fromTrace($e->getTrace());

        if ($location !== null) {
            return $location;
        }

        return [
            $this->normalizeFile($e->getFile()),
            $e->getLine(),
        ];
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     * @return array{ 0: string, 1: int|null }|null
     */
    private function fromTrace(array $trace): ?array
    {
        foreach ($trace as $frame) {
            if (isset($frame['file']) && ! $this->isVendorFile($frame['file'])) {
                return [
                    $this->normalizeFile($frame['file']),
                    $frame['line'] ?? null,
                ];
            }
        }

        return null;
    }

    private function isVendorFile(string $file): bool
    {
        return str_starts_with($file, $this->vendorPath) ||
            $file === $this->artisanPath ||
            $file === $this->publicIndexPath;
    }

    private function isInternalFile(string $file): bool
    {
        return str_starts_with($file, $this->frameworkPath) ||
            str_starts_with($file, $this->nightwatchPath) ||
            $file === $this->artisanPath ||
            $file === $this->publicIndexPath;
    }

    public function normalizeFile(string $file): string
    {
        if (! str_starts_with($file, $this->basePath)) {
            return $file;
        }

        return substr($file, strlen($this->basePath));
    }

    public function setBasePath(string $path): self
    {
        $this->basePath = $path.DIRECTORY_SEPARATOR;
        $this->artisanPath = join_paths($path, 'artisan');
        $this->vendorPath = join_paths($path, 'vendor');
        $this->nightwatchPath = join_paths($this->vendorPath, 'laravel', 'nightwatch');
        $this->frameworkPath = join_paths($this->vendorPath, 'laravel', 'framework');

        return $this;
    }

    public function setPublicPath(string $path): self
    {
        $this->publicIndexPath = join_paths($path, 'index.php');

        return $this;
    }
}
