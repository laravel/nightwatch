<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\UserProvider;

it('limits the length of the user identifier', function () {
    Auth::login(new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));
    $provider = new UserProvider(app('auth'), fn () => []);

    $this->assertSame(1000, strlen(Auth::id()));
    $this->assertSame($provider->id(), str_repeat('x', 255));
});

it('can lazily retrieve the user', function () {
    $provider = new UserProvider(app('auth'), fn () => []);

    $id = $provider->id();

    Auth::login(new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));

    $this->assertSame(str_repeat('x', 255), $id->jsonSerialize());
});

it('can remember an authenticated user and limits the length of their identifier', function () {
    $provider = new UserProvider(app('auth'), fn () => []);
    $provider->remember($user = new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));

    $this->assertSame(str_repeat('x', 255), $provider->id()->jsonSerialize());
});
