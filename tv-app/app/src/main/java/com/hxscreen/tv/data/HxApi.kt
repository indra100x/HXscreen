package com.hxscreen.tv.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone
import java.util.concurrent.TimeUnit

class ApiException(val code: Int, message: String) : Exception(message)

data class PlaylistVideo(val id: String, val name: String?, val url: String)

data class AssignedPlaylist(
    val id: String,
    val name: String,
    val videos: List<PlaylistVideo>,
    /** Window start in epoch millis, or null for always-play. */
    val startMs: Long?,
    /** Window end in epoch millis, or null for always-play. */
    val endMs: Long?,
)

data class ContentFeed(
    val screenId: String,
    val screenName: String,
    val playlists: List<AssignedPlaylist>,
) {
    /** Flat video list in play order. */
    val videos: List<PlaylistVideo>
        get() = playlists.flatMap { it.videos }

    /** Earliest moment the lineup changes, or null when open-ended. */
    val nextChangeMs: Long?
        get() {
            val now = System.currentTimeMillis()
            return playlists.mapNotNull { it.endMs }
                .filter { it > now }
                .minOrNull()
        }
}

sealed interface ClaimResult {
    data object Waiting : ClaimResult
    data class Paired(val deviceToken: String, val expiresInSeconds: Long) : ClaimResult
}

data class RemoteCommand(val id: String, val action: String, val arg: Long?)

/**
 * Parse the backend's datetime strings ("2026-10-05 15:00:00" or ISO-8601)
 * as UTC epoch millis. Null in, null out. Plain SimpleDateFormat on
 * purpose: java.time needs desugaring on API 21.
 */
private fun parseServerTime(raw: String?): Long? {
    if (raw.isNullOrBlank() || raw == "null") {
        return null
    }
    val normalized = raw.trim()
        .replace('T', ' ')
        .replace("Z", "")
        .substringBefore('.')
    return try {
        val format = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
        format.timeZone = TimeZone.getTimeZone("UTC")
        format.parse(normalized)?.time
    } catch (_: Exception) {
        null
    }
}

/**
 * Minimal client for the HXscreen device API. Uses only OkHttp + org.json
 * (built into Android) so it works unchanged from API 21 upwards.
 */
class HxApi(private val baseUrl: String) {

    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .build()

    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    private fun post(path: String, body: JSONObject, token: String? = null): JSONObject {
        val builder = Request.Builder()
            .url(baseUrl.trimEnd('/') + path)
            .post(body.toString().toRequestBody(jsonMediaType))
            .header("Accept", "application/json")
        if (token != null) {
            builder.header("Authorization", "Bearer $token")
        }
        return execute(builder.build())
    }

    private fun get(path: String, token: String? = null): JSONObject {
        val builder = Request.Builder()
            .url(baseUrl.trimEnd('/') + path)
            .header("Accept", "application/json")
        if (token != null) {
            builder.header("Authorization", "Bearer $token")
        }
        return execute(builder.build())
    }

    private fun execute(request: Request): JSONObject {
        client.newCall(request).execute().use { response ->
            val text = response.body?.string().orEmpty()
            if (!response.isSuccessful) {
                val message = try {
                    JSONObject(text).optString("message", "Request failed")
                } catch (_: Exception) {
                    "Request failed"
                }
                throw ApiException(response.code, message.ifEmpty { "Request failed" })
            }
            return JSONObject(text)
        }
    }

    /** Resolve a stored video path to a playable URL. */
    fun videoUrl(path: String): String {        if (path.startsWith("http://") || path.startsWith("https://")) {
            return path
        }
        return baseUrl.trimEnd('/') + "/storage/" + path.trimStart('/')
    }

    suspend fun requestCode(deviceId: String): Pair<String, Long> = withContext(Dispatchers.IO) {
        val json = post(
            "/api/screens/request-pairing-code",
            JSONObject().put("device_id", deviceId),
        )
        json.getString("pairing_code") to json.optLong("expires_in_seconds", 600)
    }

    suspend fun claim(deviceId: String, code: String): ClaimResult = withContext(Dispatchers.IO) {
        val json = post(
            "/api/device/claim",
            JSONObject()
                .put("device_id", deviceId)
                .put("pairing_code", code),
        )
        if (!json.optBoolean("paired", false)) {
            ClaimResult.Waiting
        } else {
            ClaimResult.Paired(
                json.getString("device_token"),
                json.optLong("expires_in_seconds", 30L * 24 * 60 * 60),
            )
        }
    }

    suspend fun content(token: String): ContentFeed = withContext(Dispatchers.IO) {
        val json = get("/api/device/content?device_token=$token", token)
        val screen = json.getJSONObject("screen")
        val playlists = mutableListOf<AssignedPlaylist>()
        val array = json.optJSONArray("playlists")
        if (array != null) {
            for (i in 0 until array.length()) {
                val p = array.getJSONObject(i)
                val pivot = p.optJSONObject("pivot")
                val videos = mutableListOf<PlaylistVideo>()
                val items = p.optJSONArray("videos")
                if (items != null) {
                    for (j in 0 until items.length()) {
                        val v = items.getJSONObject(j)
                        videos += PlaylistVideo(
                            v.getString("id"),
                            v.optString("name").ifEmpty { null },
                            videoUrl(v.getString("url")),
                        )
                    }
                }
                playlists += AssignedPlaylist(
                    p.getString("id"),
                    p.optString("name", ""),
                    videos,
                    parseServerTime(pivot?.optString("start_time")),
                    parseServerTime(pivot?.optString("end_time")),
                )
            }
        }
        ContentFeed(screen.getString("id"), screen.optString("name", ""), playlists)
    }

    suspend fun heartbeat(
        screenId: String,
        token: String,
        videoId: String? = null,
        positionMs: Long? = null,
        isPlaying: Boolean? = null,
        ackCommandId: String? = null,
    ): Unit = withContext(Dispatchers.IO) {
        val body = JSONObject()
        if (videoId != null) {
            body.put("video_id", videoId)
            body.put("position_ms", positionMs ?: 0)
        }
        if (isPlaying != null) {
            body.put("is_playing", isPlaying)
        }
        if (ackCommandId != null) {
            body.put("ack_command_id", ackCommandId)
        }
        post("/api/screens/$screenId/heartbeat", body, token)
        Unit
    }

    /** Poll the dashboard's remote-control inbox. Null when empty. */
    suspend fun commands(token: String): RemoteCommand? = withContext(Dispatchers.IO) {
        val json = get("/api/device/commands?device_token=$token", token)
        val command = json.optJSONObject("command") ?: return@withContext null
        RemoteCommand(
            command.getString("id"),
            command.getString("action"),
            if (command.isNull("arg")) null else command.optLong("arg"),
        )
    }

    suspend fun refresh(token: String): Pair<String, Long> = withContext(Dispatchers.IO) {
        val json = post("/api/device/refresh", JSONObject(), token)
        json.getString("device_token") to json.optLong("expires_in_seconds", 30L * 24 * 60 * 60)
    }
}
