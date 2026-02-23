package com.atendwa.smsreader

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.database.Cursor
import android.net.Uri
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeError
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

/**
 * Bridge function: SmsReader.GetMessages
 *
 * Reads messages from the Android system SMS ContentProvider and returns
 * them as a JSON-serialisable list. Registered in nativephp.json.
 *
 * Returns:
 *   { "messages": [{ "id", "sender", "body", "timestamp" }, ...] }
 *
 * Requires android.permission.READ_SMS (declared in nativephp.json).
 * If the permission is not yet granted at runtime, this function requests
 * it and returns a PERMISSION_REQUIRED error — the caller should retry
 * after the user grants the permission.
 */
object SmsReaderFunctions {

    private const val TAG = "SmsReaderFunctions"

    class GetMessages(private val activity: FragmentActivity) : BridgeFunction {

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context: Context = activity.applicationContext

            // Explicitly check the runtime permission before querying.
            // READ_SMS is a "dangerous" permission that must be granted at runtime
            // in addition to being declared in the manifest.
            val permissionStatus = ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.READ_SMS,
            )

            if (permissionStatus != PackageManager.PERMISSION_GRANTED) {
                Log.w(TAG, "READ_SMS not granted — requesting now")
                ActivityCompat.requestPermissions(
                    activity,
                    arrayOf(
                        Manifest.permission.READ_SMS,
                        Manifest.permission.RECEIVE_SMS,
                    ),
                    1001,
                )
                throw BridgeError.PermissionRequired(
                    "READ_SMS permission has not been granted. " +
                        "A system permission dialog has been shown. " +
                        "Grant the permission and tap Sync again.",
                )
            }

            Log.d(TAG, "READ_SMS granted — querying inbox, params=$parameters")

            // JSON numbers deserialise as Double — coerce safely
            val sender = parameters["sender"] as? String
            val limit = when (val v = parameters["limit"]) {
                is Double -> v.toInt()
                is Int    -> v
                else      -> 500
            }
            val since = when (val v = parameters["since"]) {
                is Double -> v.toLong()
                is Long   -> v
                else      -> 0L
            }

            Log.d(TAG, "Querying inbox: sender=$sender, limit=$limit, sinceMs=$since")

            return try {
                val messages = queryInbox(context, sender, limit, since)
                Log.d(TAG, "Query returned ${messages.size} messages")
                BridgeResponse.success(mapOf("messages" to messages))
            } catch (e: SecurityException) {
                Log.e(TAG, "SecurityException querying SMS: ${e.message}")
                throw BridgeError.PermissionDenied("READ_SMS permission denied: ${e.message}")
            } catch (e: Exception) {
                Log.e(TAG, "Exception querying SMS: ${e.message}", e)
                throw BridgeError.ExecutionFailed("SMS read failed: ${e.message}")
            }
        }

        /**
         * Query content://sms/inbox with optional filters.
         */
        private fun queryInbox(
            context: Context,
            sender: String?,
            limit: Int,
            sinceMs: Long,
        ): List<Map<String, Any>> {
            val results  = mutableListOf<Map<String, Any>>()
            val uri      = Uri.parse("content://sms/inbox")
            val clauses  = mutableListOf<String>()
            val args     = mutableListOf<String>()

            sender?.takeIf { it.isNotBlank() }?.let {
                clauses.add("address = ?")
                args.add(it)
            }

            if (sinceMs > 0L) {
                clauses.add("date > ?")
                args.add(sinceMs.toString())
            }

            val selection     = clauses.joinToString(" AND ").ifEmpty { null }
            val selectionArgs = args.toTypedArray().takeIf { it.isNotEmpty() }

            Log.d(TAG, "ContentResolver query: uri=$uri, selection=$selection, args=${selectionArgs?.toList()}")

            val cursor: Cursor? = context.contentResolver.query(
                uri,
                arrayOf("_id", "address", "body", "date"),
                selection,
                selectionArgs,
                "date DESC LIMIT $limit",
            )

            if (cursor == null) {
                Log.w(TAG, "ContentResolver returned null cursor — permission may be missing or SMS app not default")
            }

            cursor?.use { c ->
                Log.d(TAG, "Cursor has ${c.count} rows")
                val iId      = c.getColumnIndexOrThrow("_id")
                val iAddress = c.getColumnIndexOrThrow("address")
                val iBody    = c.getColumnIndexOrThrow("body")
                val iDate    = c.getColumnIndexOrThrow("date")

                while (c.moveToNext()) {
                    results.add(mapOf(
                        "id"        to (c.getString(iId)      ?: ""),
                        "sender"    to (c.getString(iAddress)  ?: ""),
                        "body"      to (c.getString(iBody)     ?: ""),
                        "timestamp" to  c.getLong(iDate),
                    ))
                }
            }

            return results
        }
    }
}
