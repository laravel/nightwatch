<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Models\User;
use App\Notifications\MyNotification;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Attributes\WithEnv;
use RuntimeException;
use Tests\TestCase;

use function array_sum;

class OctaneTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_prepares_for_next_request(): void
    {
        $ingest = $this->fakeIngest();
        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);
        Route::get('/test', function () {
            User::get();
            Log::channel('nightwatch')->info('Hello');
            MyJob::dispatch();
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
            Mail::to('tim@laravel.com')->send(new MyMail);
            Http::get('https://nightwatch.laravel.com');
            Cache::get('user:55');
            Nightwatch::pause();
            throw new RuntimeException('Whoops!');
        });

        $this->actingAs(new GenericUser([
            'id' => 5,
        ]))->get('/test');

        $this->assertTrue(array_sum($this->core->executionState->stageDurations) > 0);
        $this->assertSame(2, $this->core->executionState->queries);
        $this->assertSame(1, $this->core->executionState->exceptions);
        $this->assertSame(1, $this->core->executionState->logs);
        $this->assertSame(1, $this->core->executionState->jobsQueued);
        $this->assertSame(1, $this->core->executionState->mail);
        $this->assertSame(1, $this->core->executionState->notifications);
        $this->assertSame(1, $this->core->executionState->outgoingRequests);
        $this->assertSame(1, $this->core->executionState->cacheEvents);
        $this->assertSame('Whoops!', $this->core->executionState->exceptionPreview);
        $this->assertSame('GET /test', $this->core->executionState->executionPreview);
        $this->assertSame(ExecutionStage::End, $this->core->executionState->stage);
        $this->assertSame('5', $this->core->executionState->user->id());

        $this->core->uuid->uuidResolver = fn () => '8B4F773A-81AB-4273-97D5-C7BECBC173BE';
        $this->core->clock->microtimeResolver = fn () => 56789;
        $this->core->prepareForRequest(Request::create('/next'));

        $this->actingAs(new GenericUser([
            'id' => 6,
        ]));

        $this->assertSame('8B4F773A-81AB-4273-97D5-C7BECBC173BE', $this->core->executionState->id()->jsonSerialize());
        $this->assertSame('8B4F773A-81AB-4273-97D5-C7BECBC173BE', $this->core->executionState->trace);
        $this->assertSame('8B4F773A-81AB-4273-97D5-C7BECBC173BE', Compatibility::getTraceIdFromContext());
        $this->assertFalse($this->core->paused());
        $this->assertSame(0, array_sum($this->core->executionState->stageDurations));
        $this->assertSame(0, $this->core->executionState->queries);
        $this->assertSame(0, $this->core->executionState->exceptions);
        $this->assertSame(0, $this->core->executionState->logs);
        $this->assertSame(0, $this->core->executionState->jobsQueued);
        $this->assertSame(0, $this->core->executionState->mail);
        $this->assertSame(0, $this->core->executionState->notifications);
        $this->assertSame(0, $this->core->executionState->outgoingRequests);
        $this->assertSame(0, $this->core->executionState->cacheEvents);
        $this->assertSame('', $this->core->executionState->exceptionPreview);
        $this->assertSame('', $this->core->executionState->executionPreview);
        $this->assertSame(56789.0, $this->core->executionState->timestamp);
        $this->assertSame(56789.0, $this->core->executionState->currentExecutionStageStartedAtMicrotime);
        $this->assertSame(ExecutionStage::BeforeMiddleware, $this->core->executionState->stage);
        $this->assertSame('6', $this->core->executionState->user->id());
    }

    #[WithEnv('LARAVEL_CLOUD', '1')]
    public function test_it_uses_cloud_request_id_as_trace(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => '00000000-0000-0000-0000-000000000099',
        ]);

        $this->core->prepareForRequest($request);

        $this->assertSame('00000000-0000-0000-0000-000000000099', $this->core->executionState->trace);
        $this->assertSame('00000000-0000-0000-0000-000000000099', $this->core->executionState->id()->jsonSerialize());
        $this->assertSame('00000000-0000-0000-0000-000000000099', Compatibility::getTraceIdFromContext());
    }

    public function test_it_falls_back_to_uuid_when_not_on_laravel_cloud(): void
    {
        $this->core->uuid->uuidResolver = fn () => '00000000-0000-0000-0000-FALLBACK0001';

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => '00000000-0000-0000-0000-000000000099',
        ]);

        $this->core->prepareForRequest($request);

        $this->assertSame('00000000-0000-0000-0000-FALLBACK0001', $this->core->executionState->trace);
        $this->assertSame('00000000-0000-0000-0000-FALLBACK0001', $this->core->executionState->id()->jsonSerialize());
        $this->assertSame('00000000-0000-0000-0000-FALLBACK0001', Compatibility::getTraceIdFromContext());
    }

    #[WithEnv('LARAVEL_CLOUD', '1')]
    public function test_it_falls_back_to_uuid_when_header_is_missing_on_laravel_cloud(): void
    {
        $this->core->uuid->uuidResolver = fn () => '00000000-0000-0000-0000-FALLBACK0002';

        $this->core->prepareForRequest(Request::create('/'));

        $this->assertSame('00000000-0000-0000-0000-FALLBACK0002', $this->core->executionState->trace);
        $this->assertSame('00000000-0000-0000-0000-FALLBACK0002', $this->core->executionState->id()->jsonSerialize());
        $this->assertSame('00000000-0000-0000-0000-FALLBACK0002', Compatibility::getTraceIdFromContext());
    }
}
