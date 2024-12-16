<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Route;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\post;

defineEnvironment(function () {
    forceRequestExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    Config::set('mail.default', 'array');
});

it('ingests mails', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Mail::to([
            'ryuta@laravel.com',
            'jess@laravel.com',
            'tim@laravel.com',
        ])->cc([
            'phillip@laravel.com',
            'jeremy@laravel.com',
        ])->bcc([
            'james@laravel.com',
        ])->send((new MyTestMail)->html('')->subject('Welcome!')->attachData('hunter2', 'password.txt'));
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.mail', 1);
    $ingest->assertLatestWrite('mail:*', [
        [
            'v' => 1,
            't' => 'mail',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('MyTestMail'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'mailer' => 'array',
            'class' => 'MyTestMail',
            'subject' => 'Welcome!',
            'to' => 3,
            'cc' => 2,
            'bcc' => 1,
            'attachments' => 1,
            'duration' => 0,
            'failed' => false,
        ],
    ]);
});

it('ingests markdown mailables', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        Mail::to('phillip@laravel.com')->send(new MyTestMarkdownMail);
    });

    $response = post('/users');
    $response->assertOk();

    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.mail', 1);
    $ingest->assertLatestWrite('mail:*', [
        [
            'v' => 1,
            't' => 'mail',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('MyTestMarkdownMail'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'mailer' => 'array',
            'class' => 'MyTestMarkdownMail',
            'subject' => 'My Test Markdown Mail',
            'to' => 1,
            'cc' => 0,
            'bcc' => 0,
            'attachments' => 0,
            'duration' => 0,
            'failed' => false,
        ],
    ]);

});

it('ignores notifications sent as MailMessages', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        NotificationFacade::send([
            User::factory()->create(),
        ], new class extends MyTestNotification
        {
            public function via(object $notifiable)
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                app()->useAppPath(app()->basePath('src'));

                return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
            }
        });
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertLatestWrite('request:0.mail', 0);
    $ingest->assertLatestWrite('request:0.notifications', 1);
    $ingest->assertWrittenTimes(1);

});

class MyTestMail extends Mailable
{
    //
}

class MyTestMarkdownMail extends Mailable
{
    public function build()
    {
        return $this->markdown('mail');
    }
}

class MyTestNotification extends Notification
{
    public function via(object $notifiable)
    {
        return ['broadcast', 'mail'];
    }

    public function toArray(object $notifiable)
    {
        return [
            'message' => 'Hello World',
        ];
    }

    public function toMail(object $notifiable)
    {
        return (new Illuminate\Mail\Mailable)
            ->subject('Hello World')
            ->to('dummy@example.com')
            ->html("<p>It's me again</p>");
    }
}
