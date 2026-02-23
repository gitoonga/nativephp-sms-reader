<?php

namespace Atendwa\SmsReader;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SmsReader — NativePHP Mobile plugin for reading device SMS messages.
 *
 * Usage:
 *   use Atendwa\SmsReader\Facades\SmsReader;
 *
 *   $messages = SmsReader::getMessages(['sender' => 'MPESA', 'limit' => 100]);
 *
 *   // Listen for incoming SMS (in a Livewire component):
 *   #[OnNative(SmsReceived::class)]
 *   public function onSms(string $sender, string $body, int $timestamp, string $id): void {}
 *
 * Bridge response format (from BridgeRouter.kt):
 *   Success: the data map directly, e.g. {"messages": [...]}
 *   Error:   {"status":"error","code":"...","message":"...","data":{}}
 */
class SmsReader
{
    /**
     * Retrieve SMS messages from the device inbox.
     *
     * Only functional inside the NativePHP Android runtime.
     * Returns an empty array silently when not on-device (web, artisan, iOS).
     * Throws RuntimeException on bridge errors (permission denied, etc.).
     *
     * @param  array{sender?: string, limit?: int, since?: int}  $options
     * @return array<int, array{id: string, sender: string, body: string, timestamp: int}>
     *
     * @throws RuntimeException when the native bridge returns an error
     */
    public function getMessages(array $options = []): array
    {
        if (! $this->isOnDevice()) {
            return [];
        }

        $raw = $this->callBridge('SmsReader.GetMessages', json_encode($options));

        Log::debug('SmsReader raw response', ['options' => $options, 'raw' => $raw]);

        if ($raw === null || $raw === false || $raw === '') {
            throw new RuntimeException('SmsReader.GetMessages not found in bridge registry. Was the plugin registered and the app rebuilt?');
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('SmsReader bridge returned invalid JSON: '.$raw);
        }

        if (isset($decoded['status']) && $decoded['status'] === 'error') {
            $code = $decoded['code'] ?? 'UNKNOWN_ERROR';
            $message = $decoded['message'] ?? 'Unknown bridge error';
            throw new RuntimeException("SmsReader bridge error [" . $code . "]: " . $message . "}");
        }

        if (! isset($decoded['messages']) || ! is_array($decoded['messages'])) {
            throw new RuntimeException('SmsReader bridge returned unexpected structure: '.$raw);
        }

        return $decoded['messages'];
    }

    /**
     * Return the raw string response from the bridge without any processing.
     * Useful for debugging — call from a Livewire action and inspect the output.
     */
    public function getRawResponse(array $options = []): ?string
    {
        if (! $this->isOnDevice()) {
            return 'nativephp_call() does not exist — not running inside NativePHP Android runtime.';
        }

        return $this->callBridge('SmsReader.GetMessages', json_encode($options));
    }

    /**
     * Retrieve messages from multiple senders in a single call.
     * Results are merged and sorted by timestamp descending.
     *
     * @param  string[]  $senders
     * @return array<int, array{id: string, sender: string, body: string, timestamp: int}>
     */
    public function getMessagesForSenders(
        array $senders,
        ?int $sinceMs = null,
        int $limitEach = 500,
    ): array {
        $all = [];

        foreach ($senders as $sender) {
            $options = ['sender' => $sender, 'limit' => $limitEach];

            if ($sinceMs !== null) {
                $options['since'] = $sinceMs;
            }

            foreach ($this->getMessages($options) as $message) {
                $all[] = $message;
            }
        }

        usort($all, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $all;
    }

    /**
     * Returns true when running inside the NativePHP Android runtime.
     * Extracted as a protected method so tests can override it.
     */
    protected function isOnDevice(): bool
    {
        return function_exists('nativephp_call');
    }

    /**
     * Invoke the native bridge function.
     * Extracted as a protected method so tests can override it.
     */
    protected function callBridge(string $method, string $payload): mixed
    {
        return nativephp_call($method, $payload);
    }
}
