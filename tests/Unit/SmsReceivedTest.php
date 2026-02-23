<?php

use Atendwa\SmsReader\Events\SmsReceived;

it('holds all four fields set in the constructor', function () {
    $event = new SmsReceived(
        sender: 'MPESA',
        body: 'SG99OX3KN8 Confirmed. Ksh500.00 sent to JOHN DOE 0712345678',
        timestamp: 1_708_693_200_000,
        id: 'MPESA_1708693200000',
    );

    expect($event->sender)->toBe('MPESA')
        ->and($event->body)->toBe('SG99OX3KN8 Confirmed. Ksh500.00 sent to JOHN DOE 0712345678')
        ->and($event->timestamp)->toBe(1_708_693_200_000)
        ->and($event->id)->toBe('MPESA_1708693200000');
});

it('has readonly properties that cannot be overwritten after construction', function () {
    $event = new SmsReceived('MPESA', 'body', 1000, 'id');

    // Attempting to overwrite a readonly property must throw an Error.
    expect(fn () => $event->sender = 'OTHER')->toThrow(Error::class)
        ->and(fn () => $event->body = 'OTHER')->toThrow(Error::class)
        ->and(fn () => $event->timestamp = 0)->toThrow(Error::class)
        ->and(fn () => $event->id = 'OTHER')->toThrow(Error::class);
});

it('accepts any sender string including short codes', function () {
    $event = new SmsReceived(
        sender: 'airtelmoney',
        body: 'Bundle purchase confirmed',
        timestamp: 999,
        id: 'airtelmoney_999',
    );

    expect($event->sender)->toBe('airtelmoney');
});

it('preserves the full body without truncation', function () {
    $longBody = str_repeat('A', 500);

    $event = new SmsReceived('MPESA', $longBody, 1000, 'x');

    expect($event->body)->toHaveLength(500);
});
