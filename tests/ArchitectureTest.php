<?php

arch()->expect('Laravel\Nightwatch')
    // We should always reference \Symfony\HttpFoundation\Response as not all
    // responses are converted or wrapped in Laravel's response class, e.g.,
    // downloads via `response()->download()` return a Symfony response class.
    ->not->toUse(\Illuminate\Http\Response::class)
    ->not->toUse(['dd', 'ddd', 'dump', 'env', 'ray']);

arch()->expect('Laravel\Nightwatch')
    ->toBeFinal()
    ->ignoring([
        'Laravel\Nightwatch\Contracts',
        Laravel\Nightwatch\Client::class,
        Laravel\Nightwatch\ExecutionStage::class,
        Laravel\Nightwatch\SensorManager::class,
    ]);
