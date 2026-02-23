<?php

namespace Atendwa\SmsReader\Events;

/**
 * Dispatched by the Android BroadcastReceiver (SmsReceiver.kt) whenever
 * a new incoming SMS arrives on the device.
 *
 * NativePHP routes the native event payload into this class and makes it
 * available to your Livewire components via the #[OnNative] attribute.
 *
 * This event is intentionally a plain data bag — it carries the raw SMS
 * with no parsing or interpretation. Your application layer decides what
 * to do with it.
 *
 * ── Listening in a Livewire component ───────────────────────────────
 *
 *   use Atendwa\SmsReader\Events\SmsReceived;
 *
 *   #[\NativePHP\Mobile\Contracts\OnNative(SmsReceived::class)]
 *   public function handleSms(
 *       string $sender,    // originating address, e.g. "MPESA"
 *       string $body,      // full reconstructed SMS text
 *       int    $timestamp, // Unix milliseconds since epoch
 *       string $id,        // "{sender}_{timestamp}" — stable per message
 *   ): void {
 *       // your parsing / storage logic here
 *   }
 */
readonly class SmsReceived
{
    public function __construct(
        public string $sender,
        public string $body,
        public int $timestamp,
        public string $id,
    ) {}
}
