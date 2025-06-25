<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Command;
use Symfony\Component\Console\Input\StringInput;
use Tests\TestCase;

use function array_shift;

class CliFilteringTest extends TestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        $this->forceCommandExecutionState();

        parent::setUp();
    }

    public function test_it_can_filter_commands(): void
    {
        $ingest = $this->fakeIngest();
        Artisan::command('first', function () {
            DB::statement('select * from users');
        });
        Artisan::command('second', function () {
            DB::statement('select * from jobs');
        });
        $keep = [true, false];
        Nightwatch::interceptCommands(function (Command $command) use (&$keep) {
            return array_shift($keep);
        });

        $status = Artisan::handle($input = new StringInput('first'));
        Artisan::terminate($input, $status);

        $this->assertTrue($this->core->sampling());

        $status = Artisan::handle($input = new StringInput('second'));
        Artisan::terminate($input, $status);

        $this->assertFalse($this->core->sampling());

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('command:0.name', 'first');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
    }
}
