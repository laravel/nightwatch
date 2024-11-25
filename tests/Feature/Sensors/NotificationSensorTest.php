<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

use function Pest\Laravel\post;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    Log::listen(dump(...));
});

it('ingests on-demand notifications', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        NotificationFacade::route('broadcast', [new Channel('test-channel')])
            ->route('mail', 'phillip@laravel.com')
            ->notify(new MyNotification);
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.notifications_sent', 2);
    $ingest->assertLatestWrite('notification:*', [
        [
            'v' => 1,
            't' => 'notification',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'group' => '',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'channel' => 'broadcast',
            'class' => 'MyNotification',
            'duration' => 0,
            'failed' => false,
        ],
        [
            'v' => 1,
            't' => 'notification',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'group' => '',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'channel' => 'mail',
            'class' => 'MyNotification',
            'duration' => 0,
            'failed' => false,
        ],
    ]);
});

it('ingests notifications for notifiables', function () {
    $ingest = fakeIngest();
    Route::post('/users', function () {
        NotificationFacade::send([
            User::factory()->create(),
            User::factory()->create(),
            User::factory()->create(),
        ], new MyNotification);
    });

    $response = post('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.notifications_sent', 6);
    $ingest->assertLatestWrite('notification:*', [
        [
            'v' => 1,
            't' => 'notification',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            'group' => '',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'channel' => 'broadcast',
            'class' => 'MyNotification',
            'duration' => 0,
            'failed' => false,
        ]
    ]);

});


class MyNotification extends Notification
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
        return (new  Illuminate\Mail\Mailable)
            ->subject('Hello World')
            ->to('dummy@example.com')
            ->html("<p>It's me again</p>");
    }

}
