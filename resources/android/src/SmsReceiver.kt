package com.atendwa.smsreader

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import com.nativephp.mobile.ui.MainActivity
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject

/**
 * Receives the android.provider.Telephony.SMS_RECEIVED broadcast and
 * dispatches a NativePHP event to the PHP/Livewire layer.
 *
 * Registered in nativephp.json under android.receivers with exported=false,
 * so only the Android OS (not other apps) can trigger it.
 *
 * This receiver is intentionally generic — it dispatches every incoming
 * SMS without filtering. Filtering by sender is the application's job;
 * doing it here would make the plugin less reusable.
 *
 * Multi-part SMS handling:
 *   Carriers split long messages (~160 char limit) into multiple PDUs.
 *   We group PDUs by originating address and concatenate their bodies
 *   before dispatching, so your PHP code always sees the complete message.
 *
 * Requires android.permission.RECEIVE_SMS (declared in nativephp.json).
 */
class SmsReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return

        val pdus = Telephony.Sms.Intents.getMessagesFromIntent(intent)
            ?: return

        val activity = MainActivity.instance ?: return

        // Group PDUs by originating address to reconstruct multi-part messages
        pdus.groupBy { it.originatingAddress }.forEach { (address, parts) ->
            val body      = parts.joinToString("") { it.messageBody ?: "" }
            val timestamp = parts.first().timestampMillis
            val id        = "${address}_${timestamp}"

            val payload = JSONObject().apply {
                put("sender",    address ?: "")
                put("body",      body)
                put("timestamp", timestamp)
                put("id",        id)
            }

            // Dispatch to the fully-qualified PHP event class.
            // NativePHP bridges this into any Livewire component that has
            // an #[OnNative(SmsReceived::class)] listener.
            NativeActionCoordinator.dispatchEvent(
                activity,
                "Atendwa\\SmsReader\\Events\\SmsReceived",
                payload.toString(),
            )
        }
    }
}
