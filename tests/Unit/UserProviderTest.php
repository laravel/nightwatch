<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\UserProvider;

it('limits the length of the user identifier', function () {
    Auth::login(new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));
    $provider = new UserProvider(app('auth'), fn () => []);

    expect(Auth::id())->toHaveLength(1000);
    expect($provider->id())->toEqual(str_repeat('x', 255));
});

it('can lazily retrieve the user', function () {
    $provider = new UserProvider(app('auth'), fn () => []);

    $id = $provider->id();

    Auth::login(new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));

    expect($id->jsonSerialize())->toEqual(str_repeat('x', 255));
});

it('can remember an authenticated user and limits the length of their identifier', function () {
    $provider = new UserProvider(app('auth'), fn () => []);
    $provider->remember($user = new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));

    expect($provider->id()->jsonSerialize())->toEqual(str_repeat('x', 255));
});
