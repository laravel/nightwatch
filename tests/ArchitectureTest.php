<?php

arch()->expect('Laravel\Nightwatch')
    ->not->toUse([
        // We should always reference \Symfony\HttpFoundation\Response as not all
        // responses are converted or wrapped in Laravel's response class, e.g.,
        // downloads via `response()->download()` return a Symfony response class.
        \Illuminate\Http\Response::class,
        // We should be catching \Throwable.
        // We should be throwing `RuntimeException`.
        // This rule can be removed if it doesn't work out well in practice.
        \Exception::class,
    ])
    ->not->toUse(['dd', 'ddd', 'dump', 'env', 'ray']);

arch()->expect('Laravel\Nightwatch')
    ->toBeFinal()
    ->ignoring([
        'Laravel\Nightwatch\Concerns',
        'Laravel\Nightwatch\Contracts',
        Laravel\Nightwatch\RecordsBuffer::class,
        Laravel\Nightwatch\ExecutionStage::class,
        Laravel\Nightwatch\SensorManager::class,
    ]);
