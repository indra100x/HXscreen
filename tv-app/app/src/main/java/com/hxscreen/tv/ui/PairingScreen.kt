package com.hxscreen.tv.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.hxscreen.tv.data.ClaimResult
import com.hxscreen.tv.data.HxApi
import kotlinx.coroutines.delay

private const val CLAIM_POLL_MS = 3000L

/**
 * Shows the pairing code and polls `device/claim` until the dashboard
 * pairs this screen, then hands the fresh device token up.
 */
@Composable
fun PairingScreen(
    api: HxApi,
    deviceId: String,
    onPaired: (String) -> Unit,
) {
    var code by remember { mutableStateOf<String?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var attempt by remember { mutableStateOf(0) }

    LaunchedEffect(attempt) {
        error = null
        try {
            val (freshCode, _) = api.requestCode(deviceId)
            code = freshCode
            while (true) {
                delay(CLAIM_POLL_MS)
                when (val result = api.claim(deviceId, freshCode)) {
                    is ClaimResult.Waiting -> Unit // keep polling
                    is ClaimResult.Paired -> {
                        onPaired(result.deviceToken)
                        return@LaunchedEffect
                    }
                }
            }
        } catch (e: Exception) {
            error = e.message ?: "Connection failed"
        }
    }

    Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(48.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Text("HXscreen", fontSize = 28.sp, color = MaterialTheme.colorScheme.onBackground)
            Spacer(Modifier.height(16.dp))
            Text(
                "Enter this code in the dashboard to pair this screen",
                color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.7f),
            )
            Spacer(Modifier.height(32.dp))
            if (code != null) {
                Text(
                    code!!,
                    fontSize = 96.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 8.sp,
                    color = MaterialTheme.colorScheme.onBackground,
                )
            } else if (error == null) {
                CircularProgressIndicator()
            }
            Spacer(Modifier.height(24.dp))
            if (error != null) {
                Text(error!!, color = MaterialTheme.colorScheme.error)
                Spacer(Modifier.height(16.dp))
                Button(onClick = { attempt++ }) {
                    Text("Retry")
                }
            }
            Spacer(Modifier.height(32.dp))
            Text(
                "Device: $deviceId",
                fontSize = 14.sp,
                color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.5f),
            )
        }
    }
}
