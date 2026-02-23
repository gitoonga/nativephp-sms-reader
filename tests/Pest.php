<?php

use Atendwa\SmsReader\SmsReader;
use Atendwa\SmsReader\Tests\TestCase;

uses(TestCase::class)->in('Unit');

// ─────────────────────────────────────────────────────────────────────────────
//  Bridge helpers
//
//  Tests that cover on-device behaviour (error handling, response parsing, etc.)
//  cannot call the real nativephp_call() — it only exists inside the NativePHP
//  Android runtime. Instead they use a thin anonymous subclass that overrides
//  the two protected hook methods added precisely for this purpose:
//
//    protected function isOnDevice(): bool
//    protected function callBridge(string $method, string $payload): mixed
//
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a reader that simulates being on-device and always returns $response
 * from the bridge, regardless of which method or payload is requested.
 */
function fakeReader(mixed $response): SmsReader
{
    return new class($response) extends SmsReader
    {
        public function __construct(private readonly mixed $fakeResponse) {}

        protected function isOnDevice(): bool
        {
            return true;
        }

        protected function callBridge(string $method, string $payload): mixed
        {
            return $this->fakeResponse;
        }
    };
}

/**
 * Create a reader that simulates being on-device and delegates each bridge
 * call to $fn(method, payload). Use this when different senders need
 * different responses (e.g. getMessagesForSenders tests).
 */
function fakeReaderWith(callable $fn): SmsReader
{
    return new class($fn) extends SmsReader
    {
        public function __construct(private readonly mixed $fn) {}

        protected function isOnDevice(): bool
        {
            return true;
        }

        protected function callBridge(string $method, string $payload): mixed
        {
            return ($this->fn)($method, $payload);
        }
    };
}
