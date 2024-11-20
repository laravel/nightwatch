<?php

arch()->expect('Laravel\\Nightwatch')
    // We should always reference \Symfony\HttpFoundation\Response as not all
    // responses are converted or wrapped in Laravel's response class, e.g.,
    // downloads via `response()->download()` return a Symfony response class.
    ->not->toUse(\Illuminate\Http\Response::class)
    ->not->toUse(['dd', 'ddd', 'dump', 'ray']);
