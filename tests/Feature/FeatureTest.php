<?php

test('web routes can be reached', function () {
    $response = $this->get('/laravel-package/hello');

    $response->assertStatus(200);
    $response->assertSee('Hello World!');
});

test('api routes can be reached', function () {
    $response = $this->get('/laravel-package/api/stats');

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'success',
    ]);
});

test('dashboard can be rendered', function () {
    $response = $this->get('/laravel-package');

    $response->assertStatus(200);
    $response->assertSee('<h1>Laravel Package - Laravel</h1>', false);
});
