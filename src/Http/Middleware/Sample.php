<?php

namespace Laravel\Nightwatch\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

class Sample
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(private Core $nightwatch)
    {
        //
    }

    public static function rate(float $rate): string
    {
        $rate = (string) $rate;

        if ($rate === '0') {
            $rate = '0.0';
        }

        return static::class.':'.$rate;
    }

    public function handle(Request $request, Closure $next, float $rate): mixed
    {
        try {
            $this->nightwatch->sample($rate);
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }

        return $next($request);
    }
}
