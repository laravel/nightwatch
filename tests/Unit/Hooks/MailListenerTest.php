<?php

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Laravel\Nightwatch\Hooks\MailListener;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as MailerSentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function mail(MessageSending|MessageSent $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $event = new MessageSent(new SentMessage(new MailerSentMessage(
        new RawMessage('Hello world'), new Envelope(new Address('nightwatch@laravel.com'), [new Address('tim@laravel.com')])
    )));
    $handler = new MailListener($nightwatch);

    $handler($event);

    expect($sensor->thrown)->toBeTrue();
});
