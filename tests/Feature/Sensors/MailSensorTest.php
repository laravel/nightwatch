<?php

use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

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
        ])->send((new MyMail)->html('')->subject('Welcome!')->attachData('hunter2', 'password.txt'));
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.mail_sent', 1);
    $ingest->assertLatestWrite('mail:*', [
        [
            'v' => 1,
            't' => 'mail',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'group' => '',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'mailer' => 'array',
            'class' => 'MyMail',
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

class MyMail extends Mailable
{
    //
}
