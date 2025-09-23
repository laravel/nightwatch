<?php

namespace Tests\Feature;

use App\Jobs\MyJob;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Orchestra\Testbench\Attributes\WithEnv;
use Tests\TestCase;

class AssociateQueueActivityWithUserIdInContextTest extends TestCase
{
    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    public function test_it_captures_user_id_in_context_when_dispatching_jobs_within_a_request(): void
    {
        $this->fakeIngest();
        $user = User::factory()->create([
            'id' => 123,
        ]);

        Route::get('/test', function () {
            MyJob::dispatch();

            return 'ok';
        });

        $response = $this->actingAs($user)->get('/test');

        $response->assertOk();
        $payloads = DB::table('jobs')->pluck('payload');
        $this->assertCount(1, $payloads);
        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_user_id":"s:3:\"123\";', $payloads[0]);
        } else {
            $this->assertStringContainsString('"nightwatch_user_id":"123"', $payloads[0]);
        }
    }

    #[WithEnv('NIGHTWATCH_FORCE_REQUEST', '1')]
    public function test_it_previous_jobs_with_unauthenticated_users_do_not_impact_future_jobs_after_a_user_has_authenticated(): void
    {
        $this->fakeIngest();
        $user = User::factory()->create([
            'id' => 123,
        ]);

        Route::get('/test', function () use ($user) {
            MyJob::dispatch();

            Auth::login($user);

            MyJob::dispatch();

            return 'ok';
        });

        $response = $this->get('/test');

        $response->assertOk();
        $payloads = DB::table('jobs')->pluck('payload');
        $this->assertCount(2, $payloads);
        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_user_id":"s:0:\"\";', $payloads[0]);
            $this->assertStringContainsString('"nightwatch_user_id":"s:3:\"123\";', $payloads[1]);
        } else {
            $this->assertStringContainsString('"nightwatch_user_id":""', $payloads[0]);
            $this->assertStringContainsString('"nightwatch_user_id":"123"', $payloads[1]);
        }
    }
}

