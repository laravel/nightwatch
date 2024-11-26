<?php

use App\Http\UserController;
use App\Jobs\MyJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    DB::table('users')->get('name');

    MyJob::dispatch();

    Notification::route('mail', 'phillip@laravel.com')->notify(new class extends \Illuminate\Notifications\Notification
    {

        public function via(object $notifiable)
        {
            return ['mail'];
        }

        public function toArray(object $notifiable)
        {
            return [
                'message' => 'Hello World',
            ];
        }

        public function toMail(object $notifiable)
        {
            return (new  Illuminate\Mail\Mailable)
                ->subject('Hello World')
                ->to('dummy@example.com')
                ->html("<p>It's me again</p>");
        }

    });

    report('Hello world!');

    return 'ok';
});
