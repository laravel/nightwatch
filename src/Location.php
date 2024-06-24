<?php

namespace Laravel\Nightwatch;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\ViewException;
use Throwable;
use Spatie\LaravelIgnition\Exceptions\ViewException as SpatieViewException;

/**
 * TODO this should be a singleton
 */
final class Location
{
    private ?int $basePathLength;

    private ?string $vendorPath;

    private ?string $artisanPath;

    private ?string $publicIndexPath;

    public function __construct(private Application $app)
    {
        //
    }

    /**
     * @return array{ 0: string, 1: int|null }
     */
    public function find(Throwable $e): array
    {
        return match (true) {
            $e instanceof ViewException => $this->fromViewException($e),
            $e instanceof \Spatie\LaravelIgnition\Exceptions\ViewException => $this->fromSpatieViewException($e),
            default => $this->fromException($e),
        };
    }

    private function fromViewException(ViewException $e): array
    {
        preg_match('/\(View: (?P<path>.*?)\)$/', $e->getMessage(), $matches);

        return [
            $this->normalizeFile($matches['path']),
            null,
        ];
    }

    private function fromSpatieViewException(SpatieViewException $e): array
    {
        return [
            $this->normalizeFile($e->getFile()),
            $e->getLine(),
        ];
    }

    /**
     * @return array{ 0: string, 1: int|null }
     */
    private function fromException(Throwable $e): array
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
        $file = $line = null;

        foreach ($trace as $frame) {
            if (array_key_exists('file', $frame) && ! $this->isVendorFile($frame['file'])) {
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
        return str_starts_with($file, $this->vendorPath()) ||
            $file === $this->artisanPath() ||
            $file === $this->publicIndexPath();
    }

    private function normalizeFile(string $file): string
    {
        return substr($file, $this->basePathLength());
    }

    private function basePathLength(): int
    {
        return $this->basePathLength ??= strlen($this->app->basePath()) + 1;
    }

    private function vendorPath(): string
    {
        return $this->vendorPath ??= $this->app->basePath('vendor');
    }

    private function artisanPath(): string
    {
        return $this->artisanPath ??= $this->app->basePath('artisan');
    }

    private function publicIndexPath(): string
    {
        return $this->publicIndexPath ??= $this->app->publicPath('index.php');
    }
}
