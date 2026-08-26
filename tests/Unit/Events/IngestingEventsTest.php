<?php

namespace Tests\Unit\Events;

use Laravel\Nightwatch\Events\IngestingEvents;
use Tests\TestCase;

class IngestingEventsTest extends TestCase
{
    public function test_it_counts_records(): void
    {
        $event = new IngestingEvents([
            ['t' => 'request'],
            ['t' => 'query'],
            ['t' => 'exception'],
        ]);

        $this->assertSame(3, $event->eventCount());
    }

    public function test_it_excludes_user_records_from_the_count(): void
    {
        $event = new IngestingEvents([
            ['t' => 'request'],
            ['t' => 'user'],
            ['t' => 'user'],
            ['t' => 'query'],
        ]);

        $this->assertSame(2, $event->eventCount());
    }

    public function test_it_returns_zero_for_no_records(): void
    {
        $event = new IngestingEvents([]);

        $this->assertSame(0, $event->eventCount());
    }

    public function test_it_returns_zero_when_only_user_records_are_present(): void
    {
        $event = new IngestingEvents([
            ['t' => 'user'],
            ['t' => 'user'],
        ]);

        $this->assertSame(0, $event->eventCount());
    }

    public function test_it_counts_records_of_an_unknown_type(): void
    {
        $event = new IngestingEvents([
            ['t' => 'some-future-record-type'],
            ['t' => 'another-one'],
        ]);

        $this->assertSame(2, $event->eventCount());
    }
}
