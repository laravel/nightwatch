<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use RuntimeException;
use Tests\TestCase;

use function str_repeat;
use function strlen;

class UserProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_limits_the_length_of_the_user_identifier(): void
    {
        Auth::login(new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));

        $this->assertSame(1000, strlen(Auth::id()));
        $this->assertSame($this->core->executionState->user->id(), str_repeat('x', 255));
    }

    public function test_it_can_lazily_retrieve_the_user(): void
    {
        $id = $this->core->executionState->user->id();

        Auth::login(new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));

        $this->assertSame(str_repeat('x', 255), $id->jsonSerialize());
    }

    public function test_it_can_remember_an_authenticated_user_and_limits_the_length_of_their_identifier(): void
    {
        Auth::login((new User([
            'id' => str_repeat('x', 1000),
        ]))->setKeyType('string'));
        Auth::logout();

        $this->assertSame(str_repeat('x', 255), $this->core->executionState->user->id());
    }

    public function test_it_handles_exceptions_occuring_while_lazily_resolving_the_user(): void
    {
        $ingest = $this->fakeIngest();
        DB::statement('select * from users');
        DB::statement('select * from users');

        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWriteRecordCount(2);
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
        $ingest->assertLatestWrite('query:1.sql', 'select * from users');
    }

    public function test_it_only_reports_exceptions_occurring_while_resolving_user_ids_once_after_user_is_available(): void
    {
        $ingest = $this->fakeIngest();
        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        DB::statement('select * from users');
        DB::statement('select * from users');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWriteRecordCount(3);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
        $ingest->assertLatestWrite('query:1.sql', 'select * from users');
    }

    public function test_it_only_reports_exceptions_occurring_while_resolving_user_ids_once_regardless_of_where_resolving_occurs(): void
    {
        $ingest = $this->fakeIngest();

        DB::statement('select * from users');
        DB::statement('select * from users');

        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        DB::statement('select * from users');
        DB::statement('select * from users');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWriteRecordCount(5);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
        $ingest->assertLatestWrite('query:1.sql', 'select * from users');
        $ingest->assertLatestWrite('query:2.sql', 'select * from users');
        $ingest->assertLatestWrite('query:3.sql', 'select * from users');
    }

    public function test_it_allows_reporting_exceptions_occurring_while_resolving_user_ids_again_after_flush(): void
    {
        $ingest = $this->fakeIngest();
        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        DB::statement('select * from users');
        DB::statement('select * from users');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWriteRecordCount(3);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
        $ingest->assertLatestWrite('query:1.sql', 'select * from users');

        $this->core->flush();
        DB::statement('select * from users');
        DB::statement('select * from users');
        $ingest->digest();

        $ingest->assertWrittenTimes(2);
        $ingest->assertLatestWriteRecordCount(3);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
        $ingest->assertLatestWrite('query:1.sql', 'select * from users');
    }

    public function test_the_user_id_can_be_customized(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => User::all());
        $user = User::make([
            'id' => '456',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => [
            'id' => '123-'.$user->getAuthIdentifier(),
            'name' => $user->name,
            'username' => $user->email,
        ]);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:0.id', '123-456');
        $ingest->assertLatestWrite('query:0.user', '123-456');
        $ingest->assertLatestWrite('request:0.user', '123-456');
    }

    public function test_it_allows_the_id_to_be_omitted_when_customizing(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => User::all());
        $user = User::make([
            'id' => '456',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => [
            'name' => $user->name,
            'username' => $user->email,
        ]);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:0.id', '456');
        $ingest->assertLatestWrite('query:0.user', '456');
        $ingest->assertLatestWrite('request:0.user', '456');
    }

    public function test_the_user_id_can_be_customized_when_the_user_logs_out(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/logout', function () {
            Auth::logout();
            User::all();
        });
        $user = User::make([
            'id' => '456',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => [
            'id' => '123-'.$user->getAuthIdentifier(),
            'name' => $user->name,
            'username' => $user->email,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:0.id', '123-456');
        $ingest->assertLatestWrite('query:0.user', '123-456');
        $ingest->assertLatestWrite('request:0.user', '123-456');
    }

    public function test_the_id_can_be_omitted_when_customizing_and_the_user_logs_out(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/logout', function () {
            Auth::logout();
            User::all();
        });
        $user = User::make([
            'id' => '456',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => [
            'name' => $user->name,
            'username' => $user->email,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:0.id', '456');
        $ingest->assertLatestWrite('query:0.user', '456');
        $ingest->assertLatestWrite('request:0.user', '456');
    }

    public function test_it_doesnt_call_the_resolver_multiple_times(): void
    {
        $this->fakeIngest();
        Route::get('/users', fn () => User::all());
        $user = User::make();
        $calls = 0;
        Nightwatch::user(function (Authenticatable $user) use (&$calls) {
            $calls++;

            return [
                'name' => $user->name,
                'username' => $user->email,
            ];
        });

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $this->assertSame(1, $calls);
    }

    public function test_it_doesnt_call_the_resolver_multiple_times_when_logging_out(): void
    {
        $this->fakeIngest();
        Route::post('/logout', function () {
            Auth::logout();
            User::all();
        });
        $user = User::make();
        $calls = 0;
        Nightwatch::user(function (Authenticatable $user) use (&$calls) {
            $calls++;

            return [
                'name' => $user->name,
                'username' => $user->email,
            ];
        });

        $response = $this->actingAs($user)->post('/logout');

        $response->assertOk();
        $this->assertSame(1, $calls);
    }
}
