<?php

namespace Tests\Unit;

use Laravel\NightwatchAgent\Payload;
use Laravel\NightwatchAgent\StreamBuffer;
use Tests\TestCase;

use function str_repeat;

class StreamBufferTest extends TestCase
{
    public function test_it_can_pull_an_empty_buffer(): void
    {
        $buffer = new StreamBuffer(100);

        $this->assertSame('{"records":[]}', gzdecode($buffer->pull()));
    }

    public function test_it_can_write_and_pull_a_single_record(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('28:v1:a1b2c3d:[{"t":"request"}]');

        $buffer->write($payload);

        $this->assertSame('{"records":[{"t":"request"}]}', gzdecode($buffer->pull()));
    }

    public function test_it_can_write_and_pull_two_records(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('28:v1:a1b2c3d:[{"t":"request"}]');
        $buffer->write($payload);
        $payload = new Payload;
        $payload->append('28:v1:a1b2c3d:[{"u":"request"}]');
        $buffer->write($payload);

        $this->assertSame('{"records":[{"t":"request"},{"u":"request"}]}', gzdecode($buffer->pull()));
    }

    public function test_it_can_write_and_pull_many_records(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('20:v1:a1b2c3d:[{"id":1}]');
        $buffer->write($payload);
        $payload = new Payload;
        $payload->append('20:v1:a1b2c3d:[{"id":2}]');
        $buffer->write($payload);
        $payload = new Payload;
        $payload->append('20:v1:a1b2c3d:[{"id":3}]');
        $buffer->write($payload);
        $payload = new Payload;
        $payload->append('20:v1:a1b2c3d:[{"id":4}]');
        $buffer->write($payload);

        $this->assertSame('{"records":[{"id":1},{"id":2},{"id":3},{"id":4}]}', gzdecode($buffer->pull()));
    }

    public function test_it_has_not_reached_threshold_when_empty(): void
    {
        $buffer = new StreamBuffer(100);

        $this->assertFalse($buffer->reachedThreshold());
    }

    public function test_it_has_not_reached_threshold_when_under_threshold(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('119:v1:a1b2c3d:['.str_repeat('a', 99).']');

        $buffer->write($payload);

        $this->assertFalse($buffer->reachedThreshold());
    }

    public function test_it_has_reached_threshold_when_length_matches_threshold(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('120:v1:a1b2c3d:['.str_repeat('a', 100).']');

        $buffer->write($payload);

        $this->assertTrue($buffer->reachedThreshold());
    }

    public function test_it_has_reached_threshold_when_length_is_over_threshold(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('121:v1:a1b2c3d:['.str_repeat('a', 101).']');

        $buffer->write($payload);

        $this->assertTrue($buffer->reachedThreshold());
    }

    public function test_it_pulling_resets_reached_threshold_state(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('121:v1:a1b2c3d:['.str_repeat('a', 101).']');

        $buffer->write($payload);
        $this->assertTrue($buffer->reachedThreshold());
        $buffer->pull();

        $this->assertFalse($buffer->reachedThreshold());
    }

    public function test_it_empties_the_buffer_while_pulling(): void
    {
        $buffer = new StreamBuffer(100);
        $payload = new Payload;
        $payload->append('28:v1:a1b2c3d:[{"t":"request"}]');

        $buffer->write($payload);

        $this->assertSame('{"records":[{"t":"request"}]}', gzdecode($buffer->pull()));
        $this->assertSame('{"records":[]}', gzdecode($buffer->pull()));
    }
}
