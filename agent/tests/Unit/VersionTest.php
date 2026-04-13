<?php

namespace Tests\Unit;

use Symfony\Component\Process\Process;
use Tests\TestCase;

use function end;
use function explode;
use function trim;

class VersionTest extends TestCase
{
    public function test_version_is_up_to_date(): void
    {
        $process = Process::fromShellCommandline("git -c 'versionsort.suffix=-' ls-remote --tags --sort='v:refname' https://github.com/laravel/nightwatch.git 'v*.*.*'", __DIR__);

        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        $this->assertMatchesRegularExpression('/.*refs\/tags\/v\d+\.\d+\.\d+$/', $output);
        $bits = explode('refs/tags/', $output);
        $version = end($bits);
        $this->assertSame($version, 'v'.self::packageVersion());
    }
}
