<?php

use Atendwa\SmsReader\SmsReader;

// ─────────────────────────────────────────────────────────────────────────────
//  getMessages() — non-runtime (web / artisan / CI)
// ─────────────────────────────────────────────────────────────────────────────

it('returns an empty array when not running on device', function () {
    // nativephp_call() does not exist in a plain PHP test environment,
    // so getMessages() should return [] silently without throwing.
    $reader = new SmsReader;

    expect($reader->getMessages())->toBe([])
        ->and($reader->getMessages(['sender' => 'MPESA', 'limit' => 10]))->toBe([]);
});

// ─────────────────────────────────────────────────────────────────────────────
//  getMessages() — bridge error cases (simulated on-device)
// ─────────────────────────────────────────────────────────────────────────────

it('throws when bridge returns null (function not in registry)', function () {
    fakeReader(null)->getMessages(['sender' => 'MPESA']);
})->throws(RuntimeException::class, 'not found in bridge registry');

it('throws when bridge returns false', function () {
    fakeReader(false)->getMessages();
})->throws(RuntimeException::class, 'not found in bridge registry');

it('throws when bridge returns an empty string', function () {
    fakeReader('')->getMessages();
})->throws(RuntimeException::class, 'not found in bridge registry');

it('throws when bridge returns invalid JSON', function () {
    fakeReader('not-valid-json')->getMessages();
})->throws(RuntimeException::class, 'invalid JSON');

it('throws when bridge returns a PERMISSION_REQUIRED error payload', function () {
    $payload = '{"status":"error","code":"PERMISSION_REQUIRED","message":"READ_SMS permission has not been granted"}';
    fakeReader($payload)->getMessages();
})->throws(RuntimeException::class, 'PERMISSION_REQUIRED');

it('throws when bridge returns a PERMISSION_DENIED error payload', function () {
    $payload = '{"status":"error","code":"PERMISSION_DENIED","message":"Permission was denied by the user"}';
    fakeReader($payload)->getMessages();
})->throws(RuntimeException::class, 'PERMISSION_DENIED');

it('throws when bridge returns an error payload with unknown code', function () {
    fakeReader('{"status":"error"}')->getMessages();
})->throws(RuntimeException::class, 'UNKNOWN_ERROR');

it('throws when bridge returns JSON without a messages key', function () {
    fakeReader('{"data":{"messages":[]}}')->getMessages();
})->throws(RuntimeException::class, 'unexpected structure');

it('throws when messages value is not an array', function () {
    fakeReader('{"messages":"not-an-array"}')->getMessages();
})->throws(RuntimeException::class, 'unexpected structure');

// ─────────────────────────────────────────────────────────────────────────────
//  getMessages() — happy path
// ─────────────────────────────────────────────────────────────────────────────

it('returns the messages array on a valid bridge response', function () {
    $payload = '{"messages":[{"id":"ABC_1000","sender":"MPESA","body":"Confirmed. Ksh500","timestamp":1000}]}';

    $messages = fakeReader($payload)->getMessages(['sender' => 'MPESA']);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['id'])->toBe('ABC_1000')
        ->and($messages[0]['sender'])->toBe('MPESA')
        ->and($messages[0]['body'])->toBe('Confirmed. Ksh500')
        ->and($messages[0]['timestamp'])->toBe(1000);
});

it('returns an empty array when the bridge reports zero messages', function () {
    expect(fakeReader('{"messages":[]}')->getMessages())->toBe([]);
});

it('returns all messages when the bridge returns multiple', function () {
    $payload = json_encode(['messages' => [
        ['id' => 'A', 'sender' => 'MPESA', 'body' => 'First', 'timestamp' => 1000],
        ['id' => 'B', 'sender' => 'MPESA', 'body' => 'Second', 'timestamp' => 2000],
        ['id' => 'C', 'sender' => 'MPESA', 'body' => 'Third', 'timestamp' => 3000],
    ]]);

    expect(fakeReader($payload)->getMessages())->toHaveCount(3);
});

// ─────────────────────────────────────────────────────────────────────────────
//  getRawResponse() — non-runtime
// ─────────────────────────────────────────────────────────────────────────────

it('returns a descriptive string when not on device', function () {
    expect((new SmsReader)->getRawResponse())
        ->toContain('nativephp_call() does not exist');
});

// ─────────────────────────────────────────────────────────────────────────────
//  getRawResponse() — simulated on-device
// ─────────────────────────────────────────────────────────────────────────────

it('returns the raw bridge string without any processing', function () {
    $raw = '{"messages":[{"id":"X","sender":"MPESA","body":"Raw test","timestamp":1000}]}';

    expect(fakeReader($raw)->getRawResponse(['sender' => 'MPESA', 'limit' => 1]))->toBe($raw);
});

it('returns null from bridge as null', function () {
    expect(fakeReader(null)->getRawResponse())->toBeNull();
});

it('returns a malformed string without throwing', function () {
    expect(fakeReader('not-json')->getRawResponse())->toBe('not-json');
});

// ─────────────────────────────────────────────────────────────────────────────
//  getMessagesForSenders()
// ─────────────────────────────────────────────────────────────────────────────

it('merges messages from multiple senders', function () {
    $responses = [
        'MPESA'       => ['messages' => [['id' => 'M1', 'sender' => 'MPESA', 'body' => 'MPESA msg', 'timestamp' => 1000]]],
        'airtelmoney' => ['messages' => [['id' => 'A1', 'sender' => 'airtelmoney', 'body' => 'Airtel msg', 'timestamp' => 2000]]],
    ];

    $reader = fakeReaderWith(function (string $method, string $payload) use ($responses) {
        $options = json_decode($payload, true);

        return json_encode($responses[$options['sender']] ?? ['messages' => []]);
    });

    $messages = $reader->getMessagesForSenders(['MPESA', 'airtelmoney']);

    expect($messages)->toHaveCount(2);
});

it('sorts merged messages by timestamp descending', function () {
    $responses = [
        'MPESA'       => ['messages' => [['id' => 'M1', 'sender' => 'MPESA', 'body' => '', 'timestamp' => 1000]]],
        'airtelmoney' => ['messages' => [['id' => 'A1', 'sender' => 'airtelmoney', 'body' => '', 'timestamp' => 3000]]],
        'EQUITY'      => ['messages' => [['id' => 'E1', 'sender' => 'EQUITY', 'body' => '', 'timestamp' => 2000]]],
    ];

    $reader = fakeReaderWith(function (string $method, string $payload) use ($responses) {
        $options = json_decode($payload, true);

        return json_encode($responses[$options['sender']] ?? ['messages' => []]);
    });

    $messages = $reader->getMessagesForSenders(['MPESA', 'airtelmoney', 'EQUITY']);

    expect($messages[0]['sender'])->toBe('airtelmoney') // timestamp 3000
        ->and($messages[1]['sender'])->toBe('EQUITY')   // timestamp 2000
        ->and($messages[2]['sender'])->toBe('MPESA');   // timestamp 1000
});

it('passes sinceMs and limitEach to each sender query', function () {
    $captured = [];

    $reader = fakeReaderWith(function (string $method, string $payload) use (&$captured) {
        $captured[] = json_decode($payload, true);

        return '{"messages":[]}';
    });

    $reader->getMessagesForSenders(['MPESA', 'airtelmoney'], sinceMs: 1_000_000, limitEach: 5);

    expect($captured[0])->toMatchArray(['sender' => 'MPESA', 'limit' => 5, 'since' => 1_000_000])
        ->and($captured[1])->toMatchArray(['sender' => 'airtelmoney', 'limit' => 5, 'since' => 1_000_000]);
});

it('omits since from the query when sinceMs is null', function () {
    $captured = [];

    $reader = fakeReaderWith(function (string $method, string $payload) use (&$captured) {
        $captured[] = json_decode($payload, true);

        return '{"messages":[]}';
    });

    $reader->getMessagesForSenders(['MPESA'], sinceMs: null, limitEach: 10);

    expect($captured[0])->not->toHaveKey('since')
        ->and($captured[0]['limit'])->toBe(10);
});

it('returns an empty array when all senders have no messages', function () {
    expect(
        fakeReader('{"messages":[]}')->getMessagesForSenders(['MPESA', 'airtelmoney'])
    )->toBe([]);
});
