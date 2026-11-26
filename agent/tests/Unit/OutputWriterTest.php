<?php

namespace Tests\Unit;

use Laravel\NightwatchAgent\Loop;
use Laravel\NightwatchAgent\OutputWriter;
use React\EventLoop\StreamSelectLoop;
use Tests\TestCase;

use function fread;
use function str_repeat;
use function stream_get_meta_data;
use function stream_set_blocking;
use function stream_socket_pair;
use function strlen;

class OutputWriterTest extends TestCase
{
    public function test_it_writes_from_the_loop_without_making_the_stream_non_blocking(): void
    {
        [$writeEnd, $readEnd] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP) ?: $this->fail('Unable to create a socket pair.');
        stream_set_blocking($readEnd, false);
        $loop = new Loop(new StreamSelectLoop);
        $writer = new OutputWriter($loop, $writeEnd, async: true);
        $message = str_repeat('x', 500_000)."\n";
        $received = '';
        $blockedWhileRunning = null;

        $loop->addReadStream($readEnd, static function () use ($readEnd, $loop, $message, &$received) {
            $received .= fread($readEnd, 65536);

            if (strlen($received) === strlen($message)) {
                $loop->stop();
            }
        });
        $loop->addTimer(0, static function () use ($writer, $writeEnd, $message, &$blockedWhileRunning) {
            $writer->write($message);
            $blockedWhileRunning = stream_get_meta_data($writeEnd)['blocked'];
        });
        $loop->addTimer(5, static fn () => $loop->stop());
        $loop->run();

        $this->assertTrue($blockedWhileRunning);
        $this->assertTrue(stream_get_meta_data($writeEnd)['blocked']);
        $this->assertSame($message, $received);
    }

    public function test_it_writes_pending_loop_output_before_synchronous_output(): void
    {
        [$writeEnd, $readEnd] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP) ?: $this->fail('Unable to create a socket pair.');
        $loop = new Loop(new StreamSelectLoop);
        $writer = new OutputWriter($loop, $writeEnd, async: true);

        $loop->addTimer(0, static function () use ($writer, $loop) {
            $writer->write("before stop\n");
            $loop->stop();
        });
        $loop->run();
        $writer->write("after stop\n");

        $this->assertSame("before stop\nafter stop\n", fread($readEnd, 1024));
    }
}
