plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Override with: ./gradlew assembleDebug -PhxServerUrl=http://192.168.1.10:8000
val serverUrl: String = (project.findProperty("hxServerUrl") as? String)
    ?: "http://10.0.2.2:8000"

android {
    namespace = "com.hxscreen.tv"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.hxscreen.tv"
        // API 21 = Android 5.0. Covers every Android TV still in use.
        // Media3 is pinned to 1.8.x: 1.9+ requires minSdk 23.
        minSdk = 21
        targetSdk = 35
        versionCode = 1
        versionName = "1.0"

        buildConfigField("String", "SERVER_URL", "\"$serverUrl\"")
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
    composeOptions {
        // Pairs with compose-bom 2024.06.00 (Compose 1.7) on Kotlin 1.9.24.
        kotlinCompilerExtensionVersion = "1.5.14"
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
}

dependencies {
    val composeBom = platform("androidx.compose:compose-bom:2024.06.00")
    implementation(composeBom)
    androidTestImplementation(composeBom)

    // Core UI (minSdk 21-safe)
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.activity:activity-compose:1.9.2")
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.ui:ui-graphics")
    implementation("androidx.compose.ui:ui-tooling-preview")
    implementation("androidx.compose.foundation:foundation")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.lifecycle:lifecycle-runtime-compose:2.8.3")

    // Playback — pinned: Media3 1.9+ needs minSdk 23.
    implementation("androidx.media3:media3-exoplayer:1.8.1")
    implementation("androidx.media3:media3-ui:1.8.1")

    // Networking + JSON (org.json is built into Android)
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")

    debugImplementation("androidx.compose.ui:ui-tooling")
    debugImplementation("androidx.compose.ui:ui-test-manifest")
}
