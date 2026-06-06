# Aplicația Android Launcher (tabletă) — specificație

Aplicația de tabletă are exact rolul launcher-ului Moviik (am analizat APK-ul lor): un **WebView în mod kiosk** care deschide pagina dispenserului/afișajului prin cheia de conectare și, la nevoie, **tipărește bonul pe o imprimantă termică USB/Bluetooth** printr-o punte JavaScript ↔ nativ.

> Nu e nevoie de ea pentru a testa sistemul (modul „browser" și „rețea" funcționează fără). E necesară doar pentru tablete cu imprimantă USB/Bluetooth în mod kiosk.

## Arhitectură (identică conceptual cu Moviik)
```
[Server PHP]  ──HTTP──>  [WebView pe tabletă]  ──JS bridge──>  [cod nativ Kotlin]  ──USB/BT──>  [imprimantă ESC/POS]
   (dispenser)              (pagina /launcher?key=...)            (Android.print)        (Bixolon etc.)
```
Moviik folosește: WebView + `addJavascriptInterface`, **CSN Printer SDK** (ESC/POS generic USB/serial/Bluetooth/rețea), randare bon ca HTML → bitmap, și **Lock Task Mode** (kiosk). Replicăm același tipar.

## Ce face aplicația
1. La prima pornire cere **URL-ul serverului** + **cheia de conectare** (sau scanezi un QR de configurare). Le salvează.
2. Deschide `https://SERVER/launcher?key=CHEIE` într-un WebView pe tot ecranul.
3. Expune obiectul nativ `AndroidPrinter` în pagina web (vezi contractul mai jos).
4. Rulează în **kiosk** (Lock Task) ca utilizatorul să nu poată ieși din aplicație.
5. (Opțional) verifică periodic o adresă pentru update de versiune.

## Contractul punții JS (ce cheamă pagina web)
Pagina web (dispenser) detectează `window.AndroidPrinter` și, când dispozitivul e pe mod „android", cheamă:

```js
// trimis de server ca res.escpos_b64 (octeti ESC/POS gata construiti, in base64)
if (window.AndroidPrinter && res.escpos_b64) {
    window.AndroidPrinter.printBase64(res.escpos_b64);
}
```

Metode native expuse (interfața `AndroidPrinter`):
| Metodă | Descriere |
|---|---|
| `printBase64(String b64)` | Decodează base64 → octeți ESC/POS → trimite la imprimanta conectată. |
| `printHtml(String html)` | (alternativă, ca Moviik) randează HTML într-un WebView ascuns → bitmap → printează. |
| `getStatus()` | Întoarce starea imprimantei (ready/offline/no-paper) ca JSON. |
| `setConfig(String url, String key)` | Salvează configurarea (folosit de QR de provizionare). |

## Schelet Kotlin (MainActivity)
```kotlin
class MainActivity : AppCompatActivity() {
    private lateinit var web: WebView
    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        web = WebView(this).apply {
            settings.javaScriptEnabled = true
            settings.domStorageEnabled = true
            addJavascriptInterface(Bridge(this@MainActivity), "AndroidPrinter")
            webViewClient = WebViewClient()
        }
        setContentView(web)
        val url = prefs.getString("server", "") + "/launcher?key=" + prefs.getString("key", "")
        web.loadUrl(url)
        startLockTask() // kiosk (necesita device owner sau allow-list)
    }
    class Bridge(val ctx: Context) {
        @JavascriptInterface fun printBase64(b64: String) {
            val bytes = Base64.decode(b64, Base64.DEFAULT)
            ThermalPrinter.send(ctx, bytes)   // implementare USB/Bluetooth ESC/POS
        }
        @JavascriptInterface fun getStatus(): String = ThermalPrinter.status(ctx)
    }
}
```

## Imprimanta (ESC/POS USB/Bluetooth)
Două variante de bibliotecă (oricare merge cu Bixolon):
- **DantSu/ESCPOS-ThermalPrinter-Android** (open-source, ușor de integrat) — USB și Bluetooth.
- **CSN Printer SDK** (cel folosit de Moviik) — generic, suportă USB/serial/Bluetooth/rețea cu descoperire UDP.
- Bixolon oferă și **BXL SDK** oficial, dar nu e necesar — protocolul ESC/POS e standard.

Octeții ESC/POS sunt deja generați de server (`app/core/printer.php` → `build_ticket_escpos`), deci aplicația doar îi trimite la imprimantă. Asta înseamnă că **logica bonului (layout, QR, tăiere) e una singură**, pe server, și e identică pe toate canalele.

## Kiosk (Lock Task Mode)
- Cel mai curat: setezi tableta ca **device owner** (prin Android Enterprise / `adb dpm set-device-owner`) și aplicația intră singură în Lock Task.
- Alternativ: **Screen Pinning** (Setări → Securitate → Fixare ecran) — mai simplu, dar utilizatorul îl poate dezactiva ținând butoanele.

## Update OTA (opțional, ca Moviik)
Moviik verifică un JSON cu versiunea curentă și descarcă APK-ul nou. Poți face la fel: un fișier `launcher.json` pe serverul tău cu `{ "version": 5, "url": "https://.../launcher.apk" }`, verificat la pornire.
