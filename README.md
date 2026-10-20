# SIMPEL Android V4 - Kotlin + FCM + Session Fix

Fix utama V3: project sebelumnya memiliki source Kotlin (.kt), tetapi plugin Kotlin Android belum dipasang di Gradle. Akibatnya APK dapat berhasil dibuat tetapi MainActivity Kotlin tidak dikompilasi dan aplikasi dapat langsung crash saat dibuka.

Perbaikan:
- Menambahkan `org.jetbrains.kotlin.android`.
- Menambahkan dependency `androidx.activity:activity-ktx`.
- Java/Kotlin target JVM 17.
- VersionCode 3 / VersionName 1.0.2.
- Menangani URL dari notifikasi FCM saat aplikasi dibuka.
- `google-services.json` tetap berada di `app/`.

Build GitHub Actions:
`gradle :app:assembleDebug --no-daemon`


## SIMPEL Android V4 - pengaturan notifikasi & sesi

Perubahan pada V4:
- Logo SMP Negeri 8 Bontang digunakan sebagai icon aplikasi.
- FCM notification channel menggunakan IMPORTANCE_HIGH + getar.
- Notifikasi foreground diberi getar dan notifikasi background menggunakan channel yang sama.
- WebView menandai User-Agent dengan `SIMPEL-Android/1.0` agar server dapat memberi sesi Android yang lebih panjang.
- Cookie WebView di-flush saat halaman selesai, aplikasi pause, dan aplikasi ditutup agar sesi login tetap tersimpan di perangkat.
- Token FCM tetap didaftarkan ke `/api/device/register.php` setelah sesi login tersedia.

### Penting untuk server SIMPEL
`config/auth.php` pada website perlu menerapkan kebijakan sesi panjang khusus User-Agent `SIMPEL-Android/1.0`. Patch contoh disediakan pada `server/AUTH_ANDROID_SESSION_PATCH.php`.


## V5 - Notifikasi Guru Wali + BK
VersionCode 4 / VersionName 1.0.3.
Notifikasi dari server dapat diarahkan ke Guru Wali aktif berdasarkan guru_wali_siswa dan BK.
Server integration files are provided in the separate SIMPEL server patch package.
