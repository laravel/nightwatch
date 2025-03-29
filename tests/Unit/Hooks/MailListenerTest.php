<?php

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Laravel\Nightwatch\Hooks\MailListener;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as MailerSentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

it('gracefully handles exceptions', function () {
    $thrownInMailSensor = false;
    nightwatch()->sensor->mailSensor = function () use (&$thrownInMailSensor) {
        $thrownInMailSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $event = new MessageSent(new SentMessage(new MailerSentMessage(
        new RawMessage('Hello world'), new Envelope(new Address('nightwatch@laravel.com'), [new Address('tim@laravel.com')])
    )));

    $handler = new MailListener(nightwatch());
    $handler($event);

    expect($thrownInMailSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
