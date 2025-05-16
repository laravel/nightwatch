<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Payload;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\Record;
use Tests\FakeRecord;
use Tests\TestCase;

use function array_shift;
use function str_contains;

class FilteringAndMappingTest extends TestCase
{
    public function test_it_can_filter_records()
    {
        $streamsResolver = $this->fakeTcpStreams();

        Nightwatch::filter(function (Record $record): bool {
            if ($record instanceof Query) {
                return ! str_contains($record->sql, 'Laravel 3');
            }

            return true;
        });
        DB::select('select * from users where name = "Laravel 1"');
        DB::select('select * from users where name = "Laravel 2"');
        DB::select('select * from users where name = "Laravel 3"');
        $this->core->digest();

        [$stream] = $streamsResolver();
        $stream->assertWritten(function ($value) {
            $this->assertStringContainsString('Laravel 1', $value);
            $this->assertStringContainsString('Laravel 2', $value);
            $this->assertStringNotContainsString('Laravel 3', $value);

            return true;
        });
    }

    public function test_filtered_payloads_are_always_an_array(): void
    {
        $streamsResolver = $this->fakeTcpStreams();
        $filterResult = [false, true];

        Nightwatch::filter(function (Record $record) use (&$filterResult): bool {
            return array_shift($filterResult);
        });
        $this->core->ingest->write(new FakeRecord);
        $this->core->ingest->write(new FakeRecord);
        $this->core->digest();

        [$stream] = $streamsResolver();
        $stream->assertWritten('29:'.Payload::SIGNATURE.':[{"t":"fake-record"}]');
    }

    public function test_it_filters_falsey_values()
    {
        $streamsResolver = $this->fakeTcpStreams();
        $filterResult = [null, false, '', 0, true];

        Nightwatch::filter(function (Record $record) use (&$filterResult): mixed {
            return array_shift($filterResult);
        });
        $this->core->ingest->write(new FakeRecord);
        $this->core->ingest->write(new FakeRecord);
        $this->core->ingest->write(new FakeRecord);
        $this->core->ingest->write(new FakeRecord);
        $this->core->ingest->write(new FakeRecord('accepted-record'));
        $this->core->digest();

        [$stream] = $streamsResolver();
        $stream->assertWritten('33:'.Payload::SIGNATURE.':[{"t":"accepted-record"}]');
    }

    public function test_it_has_already_resolved_lazy_values()
    {
        $this->markTestIncomplete('TODO');
    }
}
