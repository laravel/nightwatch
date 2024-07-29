<?php

namespace App\Http;

use App\Models\User;

class UserController
{
    public function index()
    {
        return User::all();
    }
}
