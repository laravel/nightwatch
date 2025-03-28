<?php

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\RecordsBuffer;
use Symfony\Component\HttpFoundation\Response;

it('gracefully handles exceptions in all three phases', function () {
    Nightwatch::handleUnrecoverableExceptionsUsing(fn () => null);
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->stage = ExecutionStage::Bootstrap;
    $thrownInRequestSensor = false;
    nightwatch()->sensor->requestSensor = function () use (&$thrownInRequestSensor) {
        $thrownInRequestSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->records = new class extends RecordsBuffer
    {
        public $thrownInFlush = false;

        public function flush(): string
        {
            $this->thrownInFlush = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle(Request::create('/test'));
    $kernel->terminate(Request::create('/test'), new Response);

    expect($thrownInStageSensor)->toBeTrue();
    expect($thrownInRequestSensor)->toBeTrue();
    expect(nightwatch()->state->records->thrownInFlush)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $thrownInStageSensor = false;
    nightwatch()->sensor->stageSensor = function () use (&$thrownInStageSensor) {
        $thrownInStageSensor = true;

        throw new RuntimeException('Whoops!');
    };
    nightwatch()->state->stage = ExecutionStage::Bootstrap;

    $kernel = new class implements HttpKernel
    {
        public function bootstrap()
        {
            //
        }

        public function handle($request)
        {
            //
        }

        public function terminate($request, $response)
        {
            //
        }

        public function getApplication()
        {
            //
        }
    };

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $handler($kernel, app());

    expect($thrownInStageSensor)->toBeFalse();
});
