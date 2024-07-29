<?php

namespace App\Http;

use App\Models\User;

final class UserController
{
    public function index()
    {
        return User::all();
    }
}
