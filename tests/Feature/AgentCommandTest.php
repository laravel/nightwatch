<?php

it('does stuff', function () {
    $response = $this->get('/laravel-package/hello');

    $response->assertStatus(200);
    $response->assertSee('Hello World!');
});
