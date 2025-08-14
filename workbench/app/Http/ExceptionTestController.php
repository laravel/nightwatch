<?php

namespace App\Http;

use App\Mail\MyMail;
use Exception;
use Illuminate\Support\Facades\Mail;

use function abort;
use function report;
use function response;

final class ExceptionTestController
{
    public function __invoke()
    {
        try {
            Mail::to('test@test.com')->send(new MyMail(['effect' => 'This explodes']));
        } catch (Exception $e) {
            report($e);

            abort(500, 'Exploding as expected');
        }

        return response()->json(['message' => 'Happy path']);
    }
}
