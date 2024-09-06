<?php

namespace App\Http;

use App\Models\User;
use Illuminate\Http\Request;

final class UserController
{
    public function index()
    {
        return User::all();
    }

    public function throw(Request $request)
    {
        User::throw();
    }
}
