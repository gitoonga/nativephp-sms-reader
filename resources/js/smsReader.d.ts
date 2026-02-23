/**
 * atendwa/sms-reader — TypeScript definitions
 */

export interface SmsMessage {
    /** Unique message identifier */
    id: string;
    /** Originating address (e.g. "MPESA", "+254700000000") */
    sender: string;
    /** Full message body */
    body: string;
    /** Unix timestamp in milliseconds */
    timestamp: number;
}

export interface GetMessagesOptions {
    /** Filter by originating address (exact match) */
    sender?: string;
    /** Maximum number of messages to return (default 500) */
    limit?: number;
    /** Only return messages newer than this Unix timestamp in milliseconds */
    since?: number;
}

export type SmsReceivedCallback = (sms: SmsMessage) => void;

/**
 * Retrieve SMS messages from the device inbox.
 *
 * @throws {Error} `SmsReader bridge error [PERMISSION_REQUIRED]: ...` — permission not yet granted
 * @throws {Error} `SmsReader bridge error [PERMISSION_DENIED]: ...` — permission denied by user
 * @throws {Error} `SmsReader bridge error [ERROR]: ...` — any other native error
 */
export declare function getMessages(options?: GetMessagesOptions): Promise<SmsMessage[]>;

/**
 * Retrieve messages from multiple senders in one call.
 * Results are merged and sorted by timestamp descending.
 *
 * @param senders    Array of originating addresses to query
 * @param sinceMs    Unix timestamp in milliseconds (null = no date filter)
 * @param limitEach  Max messages per sender (default 500)
 *
 * @throws {Error} SmsReader bridge error — see `getMessages`
 */
export declare function getMessagesForSenders(
    senders: string[],
    sinceMs?: number | null,
    limitEach?: number,
): Promise<SmsMessage[]>;

/**
 * Listen for incoming SMS messages in real time.
 * Fires whenever the Android BroadcastReceiver receives a new SMS.
 */
export declare function onSmsReceived(callback: SmsReceivedCallback): void;

/**
 * Remove a previously registered SmsReceived listener.
 */
export declare function offSmsReceived(callback: SmsReceivedCallback): void;

/**
 * The fully-qualified PHP event class name dispatched when an SMS arrives.
 * Use this to register listeners directly via `window.Native.on()`.
 */
export declare const SMS_RECEIVED_EVENT: string;
