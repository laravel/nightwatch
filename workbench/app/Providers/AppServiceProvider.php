<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\Ingests\Local\LogIngest;
use Laravel\Nightwatch\Ingests\Local\NullIngest;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        //
    }
}
