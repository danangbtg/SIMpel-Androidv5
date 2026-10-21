package id.smpn8bontang.simpel

import android.Manifest
import android.annotation.SuppressLint
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.webkit.CookieManager
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ProgressBar
import androidx.activity.ComponentActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessaging

class MainActivity : ComponentActivity() {
    private lateinit var webView: WebView
    private lateinit var progress: ProgressBar
    private var fcmToken: String? = null

    // WebView file upload / gallery
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private val fileChooserRequestCode = 1001

    private val baseUrl = "https://siswa.smpn8bontang.sch.id/"

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        progress = findViewById(R.id.progress)

        createNotificationChannel()
        requestNotificationPermission()

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            cacheMode = WebSettings.LOAD_DEFAULT
            builtInZoomControls = false
            displayZoomControls = false
            javaScriptCanOpenWindowsAutomatically = true
            allowFileAccess = true
            allowContentAccess = true
            // Penanda khusus agar server dapat menerapkan sesi Android yang panjang.
            userAgentString = buildString {
                append(userAgentString)
                append(" SIMPEL-Android/1.0")
            }
        }

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        // Penting: WebChromeClient diperlukan agar <input type="file"> pada
        // halaman SIMPEL dapat membuka Galeri/Pemilih File di Android WebView.
        webView.webChromeClient = object : WebChromeClient() {
            override fun onShowFileChooser(
                webView: WebView?,
                filePath: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                // Batalkan callback sebelumnya jika masih tertinggal.
                filePathCallback?.onReceiveValue(null)
                filePathCallback = filePath

                val chooserIntent = try {
                    fileChooserParams?.createIntent()?.apply {
                        addCategory(Intent.CATEGORY_OPENABLE)
                    }
                } catch (_: Exception) {
                    null
                }

                val intent = chooserIntent ?: Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = "*/*"
                    putExtra(Intent.EXTRA_ALLOW_MULTIPLE, false)
                }

                return try {
                    startActivityForResult(intent, fileChooserRequestCode)
                    true
                } catch (_: Exception) {
                    filePathCallback?.onReceiveValue(null)
                    filePathCallback = null
                    false
                }
            }
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val host = request.url.host ?: return false
                return if (host == "siswa.smpn8bontang.sch.id") {
                    false
                } else {
                    false
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                super.onPageFinished(view, url)
                progress.visibility = android.view.View.GONE
                CookieManager.getInstance().flush()
                registerTokenToSimPel()
            }
        }

        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                fcmToken = task.result
                registerTokenToSimPel()
            }
        }

        val notificationUrl = intent.getStringExtra("notification_url")
        webView.loadUrl(resolveUrl(notificationUrl))
    }

    /**
     * Mengembalikan hasil Galeri/Pemilih File ke <input type="file"> di WebView.
     * ACTION_OPEN_DOCUMENT tidak membutuhkan permission storage khusus karena
     * pengguna sendiri memilih file yang akan diberikan ke aplikasi.
     */
    @Deprecated("Deprecated in Android API, retained for WebView file chooser compatibility")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)

        if (requestCode != fileChooserRequestCode) return

        val callback = filePathCallback ?: return
        filePathCallback = null

        if (resultCode != RESULT_OK || data == null) {
            callback.onReceiveValue(null)
            return
        }

        val results = when {
            data.clipData != null -> {
                val clip = data.clipData!!
                Array(clip.itemCount) { index -> clip.getItemAt(index).uri }
            }
            data.data != null -> arrayOf(data.data!!)
            else -> null
        }

        callback.onReceiveValue(results)
    }

    private fun resolveUrl(path: String?): String {
        if (path.isNullOrBlank()) return baseUrl
        return if (path.startsWith("http://") || path.startsWith("https://")) {
            path
        } else {
            baseUrl.trimEnd('/') + "/" + path.trimStart('/')
        }
    }

    override fun onNewIntent(intent: android.content.Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        intent.getStringExtra("notification_url")?.let { webView.loadUrl(resolveUrl(it)) }
    }

    private fun registerTokenToSimPel() {
        val token = fcmToken ?: return
        val jsToken = org.json.JSONObject.quote(token)
        val js = """
            (function(){
              try {
                fetch('/api/device/register.php', {
                  method:'POST',
                  credentials:'include',
                  headers:{'Content-Type':'application/json'},
                  body:JSON.stringify({token:$jsToken, platform:'android'})
                }).then(function(){});
              } catch(e) {}
            })();
        """.trimIndent()
        webView.post { webView.evaluateJavascript(js, null) }
    }

    private fun requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
            != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                this,
                arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                7001
            )
        }
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "simpel_default",
                "Notifikasi SIMPEL",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Notifikasi sistem SIMPEL SMPN 8 Bontang"
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 300, 200, 300)
                setShowBadge(true)
            }
            getSystemService(NotificationManager::class.java)
                .createNotificationChannel(channel)
        }
    }

    override fun onPause() {
        CookieManager.getInstance().flush()
        super.onPause()
    }

    override fun onDestroy() {
        // Jangan meninggalkan callback <input type="file"> jika Activity ditutup.
        filePathCallback?.onReceiveValue(null)
        filePathCallback = null
        CookieManager.getInstance().flush()
        webView.destroy()
        super.onDestroy()
    }

    override fun onBackPressed() {
        if (webView.canGoBack()) webView.goBack() else super.onBackPressed()
    }
}
