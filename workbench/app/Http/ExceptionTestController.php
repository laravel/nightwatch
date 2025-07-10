<?php

namespace App\Http;

use App\Mail\MyMail;
use Illuminate\Support\Facades\Mail;

final class ExceptionTestController
{
    public function __invoke()
    {
        Mail::to('test@test.com')->send(new MyMail(['effect' => 'This explodes']));
    }
}
