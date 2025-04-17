<?php

use Illuminate\Support\Facades\Route;

Route::post('agent-auth', static fn () => [
    'token' => 'NIGHTWATCH_TOKEN',
    'expires_in' => 60,
    'refresh_in' => 30,
    'ingest_url' => 'http://127.0.0.1:8000/api/ingest',
]);

Route::post('ingest', static function () {
    if (false) {
        return response()->json([
            'message' => 'Exceeded quota',
        ], 403);
    }

    if (false) {
        return response()->json([
            'message' => 'Invalid body encoding',
        ], 403);
    }

    if (false) {
        return response()->json([
            'message' => 'Invalid JSON',
        ], 403);
    }

    if (false) {
        return response()->json([
            'message' => 'Invalid body',
        ], 403);
    }

    if (false) {
        return response()->json([
            'message' => 'Invalid records',
        ], 422);
    }

    if (false) {
        return response()->json([
            'message' => 'Unexpected error',
        ], 500);
    }

    return response()->json([
        'remaining' => 1_000,
    ], 200);
});
