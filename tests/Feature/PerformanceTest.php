<?php

use Illuminate\Support\Benchmark;

it('goes fast', function () {
    // $config = app('config');

    // Benchmark::dd([
    //     'many' => function () use ($config) {
    //         [
    //             'nightwatch.ingest.local.uri' => $uri,
    //             'nightwatch.ingest.local.connection_timeout' => $connectionTimeout,
    //             'nightwatch.ingest.local.timeout' => $timeout,
    //         ] = $config->get([
    //             'nightwatch.ingest.local.uri',
    //             'nightwatch.ingest.local.connection_timeout',
    //             'nightwatch.ingest.local.timeout',
    //         ]);
    //     },
    //     'individual' => function () use ($config) {
    //         $uri = $config->get('nightwatch.ingest.local.uri');
    //         $connectionTimeout = $config->get('nightwatch.ingest.local.connection_timeout');
    //         $timeout = $config->get('nightwatch.ingest.local.timeout');
    //     },
    //     'array' => function () use ($config) {
    //         $nightwatch = $config->get('nightwatch');
    //         $uri = $nightwatch['ingest']['local']['uri'];
    //         $connectionTimeout = $nightwatch['ingest']['local']['connection_timeout'];
    //         $timeout = $nightwatch['ingest']['local']['timeout'];
    //     },
    //     'manual' => function () use ($config) {
    //         $nightwatch = $config->all()['nightwatch'];
    //         $uri = $nightwatch['ingest']['local']['uri'];
    //         $connectionTimeout = $nightwatch['ingest']['local']['connection_timeout'];
    //         $timeout = $nightwatch['ingest']['local']['timeout'];
    //     },
    // ], 1000);

})->skip();
