# Changelog

All notable changes to `atendwa/sms-reader` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-02-23

### Added
- `SmsReader::getMessages()` — query the device inbox with optional `sender`, `limit`, and `since` filters.
- `SmsReader::getMessagesForSenders()` — batch inbox queries across multiple senders; results merged and sorted by timestamp descending.
- `SmsReader::getRawResponse()` — return the raw JSON string from the native bridge (useful for debugging).
- `SmsReceived` event — dispatched in real time via `NativeActionCoordinator` whenever a new SMS arrives.
- Android `BroadcastReceiver` (`SmsReceiver`) with multi-part (multi-PDU) SMS reconstruction.
- Android `BridgeFunction` (`SmsReaderFunctions.GetMessages`) with runtime permission handling (`READ_SMS`).
- `nativephp.json` manifest declaring permissions, receiver, and bridge function registration.
- JavaScript bridge (`resources/js/smsReader.js`) — `getMessages`, `getMessagesForSenders`, `onSmsReceived`, `offSmsReceived`, `SMS_RECEIVED_EVENT`.
- TypeScript definitions (`resources/js/smsReader.d.ts`).
- Pest test suite covering happy paths, error paths, and the `SmsReceived` event.

[Unreleased]: https://github.com/atendwa/nativephp-sms-reader/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/atendwa/nativephp-sms-reader/releases/tag/v1.0.0
