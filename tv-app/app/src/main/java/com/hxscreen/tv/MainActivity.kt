package com.hxscreen.tv

import android.os.Bundle
import android.view.KeyEvent
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import com.hxscreen.tv.data.HxApi
import com.hxscreen.tv.data.TokenStore
import com.hxscreen.tv.ui.ControlsBus
import com.hxscreen.tv.ui.PairingScreen
import com.hxscreen.tv.ui.PlayerScreen

private sealed interface UiScreen {
    data object Pairing : UiScreen
    data class Player(val token: String) : UiScreen
}

class MainActivity : ComponentActivity() {

    private val controlsBus = ControlsBus()

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        // Intercepted here (not in Compose) so a focused video surface
        // can never swallow the remote's OK key.
        if (
            event?.action == KeyEvent.ACTION_DOWN &&
            (keyCode == KeyEvent.KEYCODE_DPAD_CENTER ||
                keyCode == KeyEvent.KEYCODE_ENTER ||
                keyCode == KeyEvent.KEYCODE_NUMPAD_ENTER) &&
            controlsBus.onOk?.invoke() == true
        ) {
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        val api = HxApi(BuildConfig.SERVER_URL)
        val store = TokenStore(this)

        setContent {
            MaterialTheme(
                colorScheme = darkColorScheme(
                    background = Color.Black,
                    onBackground = Color.White,
                    error = Color(0xFFCF6679),
                ),
            ) {
                Surface(modifier = Modifier.fillMaxSize()) {
                    var screen: UiScreen by remember {
                        mutableStateOf(
                            store.token?.let { UiScreen.Player(it) }
                                ?: UiScreen.Pairing,
                        )
                    }

                    when (val current = screen) {
                        is UiScreen.Pairing -> PairingScreen(
                            api = api,
                            deviceId = store.deviceId,
                            onPaired = { token ->
                                store.saveToken(token)
                                screen = UiScreen.Player(token)
                            },
                        )
                        is UiScreen.Player -> PlayerScreen(
                            api = api,
                            token = current.token,
                            tokenSavedAt = store.tokenSavedAt,
                            store = store,
                            controlsBus = controlsBus,
                            onTokenRefreshed = { store.saveToken(it) },
                            onUnpaired = {
                                store.clear()
                                screen = UiScreen.Pairing
                            },
                        )
                    }
                }
            }
        }
    }
}
