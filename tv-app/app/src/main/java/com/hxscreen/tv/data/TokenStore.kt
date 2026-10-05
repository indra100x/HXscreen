package com.hxscreen.tv.data

import android.content.Context
import android.provider.Settings

/**
 * Device identity + token storage in private SharedPreferences
 * (available on every API level, no extra dependencies).
 */
class TokenStore(context: Context) {

    private val prefs = context.getSharedPreferences("hxscreen", Context.MODE_PRIVATE)

    /** Stable per-device id used as `device_id` with the backend. */
    val deviceId: String =
        Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)
            ?: "unknown-device"

    var token: String?
        get() = prefs.getString("device_token", null)
        private set(value) {
            prefs.edit().putString("device_token", value).apply()
        }

    var tokenSavedAt: Long
        get() = prefs.getLong("token_saved_at", 0)
        private set(value) {
            prefs.edit().putLong("token_saved_at", value).apply()
        }

    fun saveToken(token: String) {
        this.token = token
        tokenSavedAt = System.currentTimeMillis()
    }

    fun clear() {
        prefs.edit().remove("device_token").remove("token_saved_at").apply()
    }
}
