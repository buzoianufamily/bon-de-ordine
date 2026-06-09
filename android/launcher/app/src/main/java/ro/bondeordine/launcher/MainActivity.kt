package ro.bondeordine.launcher

import android.annotation.SuppressLint
import android.app.Activity
import android.app.AlertDialog
import android.os.Bundle
import android.util.Base64
import android.view.View
import android.view.WindowManager
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.Toast

/**
 * Launcher kiosk pentru "Bon de ordine".
 *  - incarca pagina dispenserului (linkul introdus de utilizator) intr-un WebView pe tot ecranul;
 *  - cand clientul apasa pe ecran si se emite un bon, pagina cheama AndroidPrinter.printBase64(...)
 *    iar aplicatia trimite octetii ESC/POS la imprimanta Bixolon conectata pe USB.
 *
 * IMPORTANT: in Backoffice -> Dispozitive, dispozitivul (dispenserul) trebuie sa aiba
 * "Mod printare = Android" ca serverul sa trimita octetii ESC/POS catre aplicatie.
 */
class MainActivity : Activity() {

    private lateinit var web: WebView
    private val prefs by lazy { getSharedPreferences("cfg", MODE_PRIVATE) }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        web = WebView(this)
        web.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            mediaPlaybackRequiresUserGesture = false
            cacheMode = WebSettings.LOAD_DEFAULT
            useWideViewPort = true
            loadWithOverviewMode = true
        }
        web.webViewClient = WebViewClient()
        web.webChromeClient = WebChromeClient()
        web.addJavascriptInterface(Bridge(), "AndroidPrinter")
        setContentView(web)

        // cere din start permisiunea pentru imprimanta USB (ca sa nu apara dialogul la primul bon)
        UsbEscPosPrinter.ensurePermission(this)

        val url = prefs.getString("url", "") ?: ""
        if (url.isBlank()) askUrl(true) else web.loadUrl(url)
    }

    override fun onResume() {
        super.onResume()
        immersive()
        try { startLockTask() } catch (_: Exception) {} // kiosk (daca e permis screen pinning / device owner)
    }

    @Suppress("DEPRECATION")
    private fun immersive() {
        web.systemUiVisibility = (View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_FULLSCREEN
                or View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                or View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN)
    }

    // Butonul "inapoi" deschide meniul de administrare (nu inchide aplicatia accidental)
    @Deprecated("Deprecated in Java")
    override fun onBackPressed() = showAdminMenu()

    private fun askUrl(first: Boolean) {
        val input = EditText(this).apply {
            hint = "https://server/launcher?key=CHEIE"
            setText(prefs.getString("url", "") ?: "")
        }
        val pad = (16 * resources.displayMetrics.density).toInt()
        val box = LinearLayout(this).apply { setPadding(pad, pad, pad, 0); addView(input) }
        AlertDialog.Builder(this)
            .setTitle("Link dispenser")
            .setMessage("Lipeste linkul paginii de bilete (Backoffice -> Dispozitive -> Deschide).")
            .setView(box)
            .setCancelable(!first)
            .setPositiveButton("Salveaza") { _, _ ->
                val u = input.text.toString().trim()
                if (u.isNotBlank()) { prefs.edit().putString("url", u).apply(); web.loadUrl(u) }
            }
            .show()
    }

    private fun showAdminMenu() {
        AlertDialog.Builder(this)
            .setTitle("Administrare")
            .setItems(
                arrayOf("Reincarca pagina", "Schimba linkul", "Test imprimanta", "Iesire din aplicatie")
            ) { _, which ->
                when (which) {
                    0 -> web.reload()
                    1 -> askUrl(false)
                    2 -> Thread {
                        val ok = UsbEscPosPrinter.testPrint(this)
                        runOnUiThread { toast(if (ok) "Tiparit OK" else "Imprimanta indisponibila") }
                    }.start()
                    3 -> { try { stopLockTask() } catch (_: Exception) {}; finish() }
                }
            }
            .show()
    }

    private fun toast(m: String) = Toast.makeText(this, m, Toast.LENGTH_SHORT).show()

    /** Obiectul expus paginii web ca window.AndroidPrinter */
    inner class Bridge {
        @JavascriptInterface
        fun printBase64(b64: String) {
            val bytes = try { Base64.decode(b64, Base64.DEFAULT) } catch (e: Exception) { return }
            Thread {
                val ok = UsbEscPosPrinter.print(this@MainActivity, bytes)
                if (!ok) runOnUiThread { toast("Eroare imprimanta") }
            }.start()
        }

        @JavascriptInterface
        fun getStatus(): String = UsbEscPosPrinter.status(this@MainActivity)

        @JavascriptInterface
        fun setConfig(url: String) {
            prefs.edit().putString("url", url).apply()
            runOnUiThread { web.loadUrl(url) }
        }
    }
}
