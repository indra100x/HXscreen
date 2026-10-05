package com.hxscreen.tv.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.viewinterop.AndroidView
import androidx.media3.common.MediaItem
import androidx.media3.common.Player
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.ui.PlayerView
import com.hxscreen.tv.data.ApiException
import com.hxscreen.tv.data.ContentFeed
import com.hxscreen.tv.data.HxApi
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive

private const val HEARTBEAT_MS = 15_000L
private const val CONTENT_REFRESH_MS = 15_000L
private const val COMMAND_POLL_MS = 2_000L
private const val REFRESH_AFTER_MS = 23L * 24 * 60 * 60 * 1000

/**
 * Fullscreen looping playback of the screen's content feed.
 *
 * Heartbeats every 30 seconds and re-fetches the playlist every 15 seconds,
 * so dashboard changes (assign, reorder, schedule) appear without
 * restarting the app. Any auth failure returns to pairing.
 */
@Composable
fun PlayerScreen(
    api: HxApi,
    token: String,
    tokenSavedAt: Long,
    onTokenRefreshed: (String) -> Unit,
    onUnpaired: () -> Unit,
) {
    val context = LocalContext.current
    var status by remember { mutableStateOf("Loading…") }

    val exoPlayer = remember {
        ExoPlayer.Builder(context).build().apply {
            repeatMode = Player.REPEAT_MODE_ALL
            playWhenReady = true
        }
    }

    DisposableEffect(Unit) {
        onDispose { exoPlayer.release() }
    }

    // Latest applied remote command: sent back as the heartbeat ack so the
    // server can clear its inbox. Kept outside the loops to survive refresh.
    var ackCommandId by remember { mutableStateOf<String?>(null) }
    // Screen id from the first content fetch, shared with the command loop
    // so it can report state immediately after acting.
    var knownScreenId by remember { mutableStateOf("") }
    // Token may rotate on startup; the loops must use the active one.
    var activeTokenState by remember { mutableStateOf(token) }

    // Remote-control inbox: pause / resume / seek from the dashboard.
    LaunchedEffect(token) {
        var appliedId: String? = null
        while (isActive) {
            delay(COMMAND_POLL_MS)
            try {
                val command = api.commands(activeTokenState)
                if (command == null) {
                    appliedId = null
                } else if (command.id != appliedId) {
                    when (command.action) {
                        "pause" -> exoPlayer.pause()
                        "resume" -> exoPlayer.play()
                        "seek_by" -> exoPlayer.seekTo(
                            (exoPlayer.currentPosition + (command.arg ?: 0) * 1000)
                                .coerceAtLeast(0),
                        )
                    }
                    appliedId = command.id
                    ackCommandId = command.id
                    // Report the fresh state right away so the dashboard's
                    // next re-sync lands on the true spot instead of waiting
                    // for the next heartbeat.
                    if (knownScreenId.isNotEmpty()) {
                        try {
                            val item = exoPlayer.currentMediaItem
                            api.heartbeat(
                                knownScreenId,
                                activeTokenState,
                                item?.mediaId,
                                if (item == null) null else exoPlayer.currentPosition,
                                exoPlayer.isPlaying,
                                command.id,
                            )
                        } catch (_: Exception) {
                            // The regular heartbeat will deliver it shortly.
                        }
                    }
                }
            } catch (_: Exception) {
                // Transient network failure: retry on the next poll.
            }
        }
    }

    LaunchedEffect(token) {
        try {
            var activeToken = token
            if (System.currentTimeMillis() - tokenSavedAt > REFRESH_AFTER_MS) {
                val (fresh, _) = api.refresh(activeToken)
                activeToken = fresh
                onTokenRefreshed(fresh)
            }
            activeTokenState = activeToken

            var screenId = ""
            var currentSignature = ""
            var nextChangeMs: Long? = null

            // Applies a fresh feed, restarting playback only when the
            // lineup (playlists, videos, or windows) actually changed.
            fun applyFeed(feed: ContentFeed) {
                screenId = feed.screenId
                knownScreenId = feed.screenId
                nextChangeMs = feed.nextChangeMs
                val signature = feed.playlists.joinToString("|") { playlist ->
                    playlist.id + ":" +
                        playlist.videos.joinToString(",") { it.id } + ":" +
                        playlist.startMs + "-" + playlist.endMs
                }
                if (signature != currentSignature) {
                    currentSignature = signature
                    val urls = feed.videos.map { it.url }
                    if (urls.isEmpty()) {
                        exoPlayer.stop()
                        exoPlayer.clearMediaItems()
                        status = "No videos assigned yet"
                    } else {
                        exoPlayer.setMediaItems(
                            urls.mapIndexed { index, url ->
                                MediaItem.Builder()
                                    .setUri(url)
                                    .setMediaId(feed.videos[index].id)
                                    .build()
                            },
                        )
                        exoPlayer.prepare()
                        exoPlayer.play()
                        status = ""
                    }
                }
            }

            applyFeed(api.content(activeToken))

            var elapsed = 0L
            while (isActive) {
                // Wake for the heartbeat, for the regular poll, or exactly
                // when the earliest playing window ends — whichever is first.
                val now = System.currentTimeMillis()
                val endIn = nextChangeMs?.let { (it - now).coerceAtLeast(0L) }
                val wait = minOf(
                    HEARTBEAT_MS,
                    (CONTENT_REFRESH_MS - elapsed).coerceAtLeast(0L),
                    endIn ?: CONTENT_REFRESH_MS,
                ).coerceAtLeast(1_000L)
                delay(wait)
                elapsed += wait

                try {
                    val currentItem = exoPlayer.currentMediaItem
                    api.heartbeat(
                        screenId,
                        activeToken,
                        currentItem?.mediaId,
                        if (currentItem == null) null else exoPlayer.currentPosition,
                        exoPlayer.isPlaying,
                        ackCommandId,
                    )
                } catch (e: ApiException) {
                    if (e.code == 401) {
                        onUnpaired()
                        return@LaunchedEffect
                    }
                }

                val windowEnded =
                    nextChangeMs?.let { System.currentTimeMillis() >= it } == true
                if (elapsed >= CONTENT_REFRESH_MS || windowEnded) {
                    elapsed = 0L
                    try {
                        applyFeed(api.content(activeToken))
                    } catch (e: ApiException) {
                        if (e.code == 401) {
                            onUnpaired()
                            return@LaunchedEffect
                        }
                    }
                }
            }
        } catch (e: ApiException) {
            if (e.code == 401) {
                onUnpaired()
            } else {
                status = e.message ?: "Connection failed"
            }
        } catch (e: Exception) {
            status = e.message ?: "Connection failed"
        }
    }

    Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { ctx ->
                    PlayerView(ctx).apply {
                        this.player = exoPlayer
                        useController = false
                    }
                },
            )
            if (status.isNotEmpty()) {
                Text(status, color = MaterialTheme.colorScheme.onBackground)
            }
        }
    }
}
