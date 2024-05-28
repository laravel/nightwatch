<?php

namespace Laravel\Package;

use Closure;

class LaravelPackage
{
    /**
     * The callback that should be used to authenticate LaravelPackage users.
     *
     * @var \Closure
     */
    public static $authUsing;

    /**
     * Determine if the given request can access the LaravelPackage dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function check($request)
    {
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authenticate LaravelPackage users.
     *
     * @return static
     */
    public static function auth(Closure $callback)
    {
        static::$authUsing = $callback;

        return new static;
    }
}
