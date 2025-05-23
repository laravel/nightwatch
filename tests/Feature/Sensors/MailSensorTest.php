<?php

namespace Tests\Feature\Sensors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Tests\TestCase;

use function hash;
use function now;

class MailSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_ingests_mails(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
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

        Event::listen(MessageSending::class, function ($event): void {
            $this->travelTo(now()->addMicroseconds(2500));
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.mail', 1);
        $ingest->assertLatestWrite('mail:*', [
            [
                'v' => 1,
                't' => 'mail',
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => Compatibility::$mailableClassNameCapturable ? hash('xxh128', 'Tests\Feature\Sensors\MyTestMail') : hash('xxh128', ''),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'mailer' => 'array',
                'class' => Compatibility::$mailableClassNameCapturable ? 'Tests\Feature\Sensors\MyTestMail' : '',
                'subject' => 'Welcome!',
                'to' => 3,
                'cc' => 2,
                'bcc' => 1,
                'attachments' => 1,
                'duration' => 2500,
                'failed' => false,
            ],
        ]);
    }

    public function test_it_ingests_markdown_mailables(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            Mail::to('phillip@laravel.com')->send(new MyTestMarkdownMail);
        });

        $response = $this->post('/users');
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
                '_group' => Compatibility::$mailableClassNameCapturable ? hash('xxh128', 'Tests\Feature\Sensors\MyTestMarkdownMail') : hash('xxh128', ''),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'mailer' => 'array',
                'class' => Compatibility::$mailableClassNameCapturable ? 'Tests\Feature\Sensors\MyTestMarkdownMail' : '',
                'subject' => 'My Test Markdown Mail',
                'to' => 1,
                'cc' => 0,
                'bcc' => 0,
                'attachments' => 0,
                'duration' => 0,
                'failed' => false,
            ],
        ]);

    }

    public function test_it_ignores_notifications_sent_as_mail_messages(): void
    {
        // If this test fails, try clearing `workbench/storage/framework/views/*`
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
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
                    return (new MailMessage)->view('mail');
                }
            });
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertLatestWrite('request:0.mail', 0);
        $ingest->assertLatestWrite('request:0.notifications', 1);
        $ingest->assertWrittenTimes(1);

    }
}

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
        return (new Mailable)
            ->subject('Hello World')
            ->to('dummy@example.com')
            ->html("<p>It's me again</p>");
    }
}
