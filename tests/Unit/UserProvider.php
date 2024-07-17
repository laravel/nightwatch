<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\UserProvider;

it('limits the length of the user identifier', function () {
    Auth::login(new GenericUser([
        'id' => str_repeat('x', 1000),
    ]));
    /** @var UserProvider */
    $provider = app(UserProvider::class);

    expect(Auth::id())->toHaveLength(1000);
    expect($provider->id())->toHaveLength(255);
});
