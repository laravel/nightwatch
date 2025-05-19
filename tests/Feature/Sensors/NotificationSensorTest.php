<?php

namespace Tests\Feature\Sensors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

use function hash;
use function now;

class NotificationSensorTest extends TestCase
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

    public function test_it_ingests_on_demand_notifications(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            NotificationFacade::route('broadcast', [new Channel('test-channel')])
                ->route('mail', 'phillip@laravel.com')
                ->notify(new MyNotification);
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.notifications', 2);
        $ingest->assertLatestWrite('notification:*', [
            [
                'v' => 1,
                't' => 'notification',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyNotification'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'channel' => 'broadcast',
                'class' => 'Tests\Feature\Sensors\MyNotification',
                'duration' => 0,
                'failed' => false,
            ],
            [
                'v' => 1,
                't' => 'notification',
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyNotification'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'channel' => 'mail',
                'class' => 'Tests\Feature\Sensors\MyNotification',
                'duration' => 2500,
                'failed' => false,
            ],
        ]);
    }

    public function test_it_ingests_notifications_for_notifiables(): void
    {
        $ingest = $this->fakeIngest();
        Route::post('/users', function (): void {
            NotificationFacade::send([
                User::factory()->create(),
                User::factory()->create(),
                User::factory()->create(),
            ], new class extends MyNotification
            {
                public function via(object $notifiable)
                {
                    return ['mail'];
                }
            });
        });

        $response = $this->post('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.notifications', 3);
        $ingest->assertLatestWrite('notification:*', [
            [
                'v' => 1,
                't' => 'notification',
                'timestamp' => 946688523.459289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyNotification@anonymous'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'channel' => 'mail',
                'class' => 'Tests\Feature\Sensors\MyNotification@anonymous',
                'duration' => 2500,
                'failed' => false,
            ],
            [
                'v' => 1,
                't' => 'notification',
                'timestamp' => 946688523.461789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyNotification@anonymous'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'channel' => 'mail',
                'class' => 'Tests\Feature\Sensors\MyNotification@anonymous',
                'duration' => 2500,
                'failed' => false,
            ],
            [
                'v' => 1,
                't' => 'notification',
                'timestamp' => 946688523.464289,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'Tests\Feature\Sensors\MyNotification@anonymous'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'POST /users',
                'execution_stage' => 'action',
                'user' => '',
                'channel' => 'mail',
                'class' => 'Tests\Feature\Sensors\MyNotification@anonymous',
                'duration' => 2500,
                'failed' => false,
            ],
        ]);
    }
}

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
        Date::setTestNow(now()->addMicroseconds(2500));

        return (new Mailable)
            ->subject('Hello World')
            ->to('dummy@example.com')
            ->html("<p>It's me again</p>");
    }
}
