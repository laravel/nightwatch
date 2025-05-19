<?php

namespace Tests\Unit;

use Laravel\NightwatchAgent\StreamBuffer;
use Tests\TestCase;

use function str_repeat;

class StreamBufferTest extends TestCase
{
    public function test_it_can_pull_an_empty_buffer(): void
    {
        $buffer = new StreamBuffer(100);

        $this->assertSame('{"records":[]}', $buffer->pull());
    }

    public function test_it_can_write_and_pull_a_single_record(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('[{"id":1}]');

        $this->assertSame('{"records":[{"id":1}]}', $buffer->pull());
    }

    public function test_it_can_write_and_pull_two_records(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('[{"id":1}]');
        $buffer->write('[{"id":2}]');

        $this->assertSame('{"records":[{"id":1},{"id":2}]}', $buffer->pull());
    }

    public function test_it_can_write_and_pull_many_records(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('[{"id":1}]');
        $buffer->write('[{"id":2}]');
        $buffer->write('[{"id":3}]');
        $buffer->write('[{"id":4}]');

        $this->assertSame('{"records":[{"id":1},{"id":2},{"id":3},{"id":4}]}', $buffer->pull());
    }

    public function test_it_has_not_reached_threshold_when_empty(): void
    {
        $buffer = new StreamBuffer(100);

        $this->assertFalse($buffer->reachedThreshold());
    }

    public function test_it_has_not_reached_threshold_when_under_threshold(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write(str_repeat('a', 99));

        $this->assertFalse($buffer->reachedThreshold());
    }

    public function test_it_has_reached_threshold_when_length_matches_threshold(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('['.str_repeat('a', 100).']');

        $this->assertTrue($buffer->reachedThreshold());
    }

    public function test_it_has_reached_threshold_when_length_is_over_threshold(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('['.str_repeat('a', 101).']');

        $this->assertTrue($buffer->reachedThreshold());
    }

    public function test_it_pulling_resets_reached_threshold_state(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('['.str_repeat('a', 101).']');
        $this->assertTrue($buffer->reachedThreshold());
        $buffer->pull();

        $this->assertFalse($buffer->reachedThreshold());
    }

    public function test_it_empties_the_buffer_while_pulling(): void
    {
        $buffer = new StreamBuffer(100);

        $buffer->write('[{"id":1}]');

        $this->assertSame('{"records":[{"id":1}]}', $buffer->pull());
        $this->assertSame('{"records":[]}', $buffer->pull());
    }
}
