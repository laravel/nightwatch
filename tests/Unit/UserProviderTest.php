<?php

namespace Tests\Unit;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\UserProvider;
use Tests\TestCase;

use function str_repeat;
use function strlen;

class UserProviderTest extends TestCase
{
    public function test_it_limits_the_length_of_the_user_identifier(): void
    {
        Auth::login(new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));
        $provider = new UserProvider($this->app['auth'], fn () => []);

        $this->assertSame(1000, strlen(Auth::id()));
        $this->assertSame($provider->id(), str_repeat('x', 255));
    }

    public function test_it_can_lazily_retrieve_the_user(): void
    {
        $provider = new UserProvider($this->app['auth'], fn () => []);

        $id = $provider->id();

        Auth::login(new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));

        $this->assertSame(str_repeat('x', 255), $id->jsonSerialize());
    }

    public function test_it_can_remember_an_authenticated_user_and_limits_the_length_of_their_identifier(): void
    {
        $provider = new UserProvider($this->app['auth'], fn () => []);
        $provider->remember($user = new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));

        $this->assertSame(str_repeat('x', 255), $provider->id()->jsonSerialize());
    }
}
