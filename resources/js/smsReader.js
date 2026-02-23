/**
 * atendwa/sms-reader — JavaScript bridge
 *
 * Exposes the SmsReader plugin to Vue, React, Inertia, or vanilla JS apps.
 *
 * Bridge calls (getMessages, getMessagesForSenders) go through NativePHP's
 * HTTP bridge at /_native/api/call. Event listening (onSmsReceived) uses
 * the window.Native event bus injected by the NativePHP web view.
 *
 * Usage:
 *   import { getMessages, getMessagesForSenders, onSmsReceived, offSmsReceived } from
 *       './vendor/atendwa/sms-reader/resources/js/smsReader.js';
 *
 *   // Read inbox
 *   const messages = await getMessages({ sender: 'MPESA', limit: 50 });
 *
 *   // Listen for incoming SMS in real time
 *   const handler = ({ sender, body, timestamp, id }) => console.log(body);
 *   onSmsReceived(handler);
 *   // later: offSmsReceived(handler);
 */

const BRIDGE_URL = '/_native/api/call';
const SMS_RECEIVED_EVENT = 'Atendwa\\SmsReader\\Events\\SmsReceived';

// ─────────────────────────────────────────────────────────────────────────────
//  Internal helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * POST a call to the NativePHP bridge and return the parsed JSON payload.
 * Throws an Error if the bridge returns a status of "error".
 *
 * @param {string} method  Fully-qualified bridge method, e.g. 'SmsReader.GetMessages'
 * @param {Object} params  Parameters forwarded to the native function
 * @returns {Promise<Object>}
 * @throws {Error} SmsReader bridge error [CODE]: message
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(BRIDGE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    });

    const result = await response.json();

    if (result?.status === 'error') {
        const code = result.code ?? 'ERROR';
        const message = result.message ?? 'Unknown bridge error';
        throw new Error(`SmsReader bridge error [${code}]: ${message}`);
    }

    return result;
}

// ─────────────────────────────────────────────────────────────────────────────
//  Public API
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retrieve SMS messages from the device inbox.
 *
 * @param {{ sender?: string, limit?: number, since?: number }} options
 *   sender  — filter by originating address (exact match)
 *   limit   — maximum messages to return (default 500)
 *   since   — only messages newer than this Unix timestamp in milliseconds
 *
 * @returns {Promise<Array<{ id: string, sender: string, body: string, timestamp: number }>>}
 * @throws {Error} SmsReader bridge error [PERMISSION_REQUIRED | PERMISSION_DENIED | ...]: message
 */
export async function getMessages(options = {}) {
    const result = await bridgeCall('SmsReader.GetMessages', options);
    return result.messages ?? [];
}

/**
 * Retrieve messages from multiple senders in one call.
 * Results are merged and sorted by timestamp descending.
 *
 * @param {string[]} senders
 * @param {number|null} sinceMs  Unix timestamp in milliseconds (null = no filter)
 * @param {number} limitEach     Max messages per sender (default 500)
 *
 * @returns {Promise<Array<{ id: string, sender: string, body: string, timestamp: number }>>}
 * @throws {Error} SmsReader bridge error [CODE]: message
 */
export async function getMessagesForSenders(senders, sinceMs = null, limitEach = 500) {
    const batches = await Promise.all(
        senders.map((sender) => {
            const options = { sender, limit: limitEach };
            if (sinceMs !== null) {
                options.since = sinceMs;
            }
            return getMessages(options);
        }),
    );

    return batches
        .flat()
        .sort((a, b) => b.timestamp - a.timestamp);
}

/**
 * Listen for incoming SMS messages in real time.
 *
 * The callback receives { sender, body, timestamp, id } whenever the Android
 * BroadcastReceiver picks up a new SMS and dispatches the SmsReceived event.
 *
 * @param {(sms: { sender: string, body: string, timestamp: number, id: string }) => void} callback
 */
export function onSmsReceived(callback) {
    window.Native?.on(SMS_RECEIVED_EVENT, callback);
}

/**
 * Remove a previously registered SmsReceived listener.
 *
 * @param {(sms: { sender: string, body: string, timestamp: number, id: string }) => void} callback
 */
export function offSmsReceived(callback) {
    window.Native?.off(SMS_RECEIVED_EVENT, callback);
}

/**
 * The fully-qualified PHP event class name dispatched when an SMS arrives.
 * Use this if you prefer to register listeners directly via window.Native.on().
 *
 * @type {string}
 */
export { SMS_RECEIVED_EVENT };
