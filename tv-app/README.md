# HXscreen TV

Android TV player for HXscreen. Runs on **Android 5.0 (API 21) and up** —
one universal APK for every ABI.

## How it works

1. The TV calls `POST /api/screens/request-pairing-code` and shows the code fullscreen.
2. It polls `POST /api/device/claim` every 3 seconds.
3. You pair it from the web dashboard (code + business).
4. The TV receives its device token, fetches `GET /api/device/content`,
   and loops the playlist videos fullscreen with ExoPlayer.
5. It sends a heartbeat every 30 seconds. Any `401` returns to the pairing
   screen. Tokens older than 23 days are refreshed at startup (they expire
   after 30 days).

## Version pins (do not bump blindly)

- `minSdk = 21` — **Media3 must stay on 1.8.x**: 1.9+ requires minSdk 23.
- Dependencies are plain OkHttp + `org.json` (built in) so nothing else
  pulls the minimum up.

## Build

Requirements: JDK 17+, Android SDK with platform 35 + build-tools.

```bash
cd tv-app
# Point the app at your backend (emulator reaches host via 10.0.2.2,
# a physical TV needs your machine's LAN IP):
./gradlew assembleDebug -PhxServerUrl=http://192.168.1.10:8000

adb install -r app/build/outputs/apk/debug/app-debug.apk
```

`ORG_GRADLE_JAVA_HOME` must point at JDK 17–21 if your default `java`
is newer, e.g. `export ORG_GRADLE_JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64`.

## Notes

- `android:usesCleartextTraffic="true"` is set for local HTTP testing.
  For production HTTPS, keep it and serve TLS — or remove the flag to
  enforce encrypted traffic.
- Android 5.x devices predate TLS 1.2-by-default: HTTPS backends need
  Play Services' `ProviderInstaller` there. Plain HTTP on a local
  network works everywhere.
