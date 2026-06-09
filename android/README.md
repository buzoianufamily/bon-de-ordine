# Aplicația Android „Bon de ordine" (kiosk + imprimantă USB)

Aplicație pentru un **mini‑PC / tabletă cu Android** așezată la intrare: deschide pe tot ecranul pagina dispenserului de bilete (linkul pe care îl introduci tu) și, când clientul apasă pe ecran și se emite un bon, **îl tipărește pe imprimanta termică Bixolon conectată pe USB** (testat conceptual cu **Bixolon BK3‑31ZC/BEG**, protocol ESC/POS standard).

Codul sursă complet este în `android/launcher/`.

---

## Cum funcționează
```
[Server PHP]  --HTTP-->  [WebView pe mini-PC]  --JS bridge-->  [cod Kotlin]  --USB-->  [Bixolon BK3-31ZC]
 (dispenser)              (pagina /launcher?key=...)        (AndroidPrinter)     (octeti ESC/POS)
```
Octeții bonului (layout, QR, tăiere) sunt construiți **pe server** (`app/core/printer.php`), identic pentru toate canalele. Aplicația doar îi trimite la imprimantă — deci bonul arată la fel peste tot.

Pagina dispenserului cheamă automat puntea nativă:
```js
if (window.AndroidPrinter && res.escpos_b64) window.AndroidPrinter.printBase64(res.escpos_b64);
```

Metode expuse paginii (interfața `AndroidPrinter`):
| Metodă | Rol |
|---|---|
| `printBase64(b64)` | decodează base64 → octeți ESC/POS → trimite pe USB |
| `getStatus()` | stare imprimantă ca JSON (`{"ready":true,...}`) |
| `setConfig(url)` | schimbă linkul încărcat (opțional, pentru provizionare) |

---

## 1) Obține APK‑ul

### Varianta A — automat, prin GitHub Actions (recomandat, fără să instalezi nimic)
1. Pune codul pe GitHub (e deja în acest repo).
2. Tab‑ul **Actions** → workflow‑ul **„Build Android APK"** → **Run workflow**.
3. Când termină, descarcă artifact‑ul **`bon-de-ordine-launcher-apk`** → conține `app-debug.apk`.

### Varianta B — local, cu Android Studio
1. Deschide folderul `android/launcher` în Android Studio (Giraffe+ / AGP 8.5).
2. **Build → Build Bundle(s) / APK(s) → Build APK(s)**.
3. APK‑ul rezultă în `android/launcher/app/build/outputs/apk/debug/app-debug.apk`.

### Varianta C — linie de comandă
```bash
cd android/launcher
gradle assembleDebug      # sau ./gradlew assembleDebug daca ai wrapper-ul
```

---

## 2) Instalează pe mini‑PC
1. Copiază `app-debug.apk` pe mini‑PC (USB stick / link) și deschide‑l (permite „Surse necunoscute").
2. La prima pornire, aplicația cere **linkul dispenserului** — lipești linkul din **Backoffice → Dispozitive → (dispenserul tău) → Deschide** (forma `https://site/launcher?key=CHEIE`).
3. Gata: pagina se încarcă pe tot ecranul.

> Butonul **Înapoi** deschide meniul de administrare (Reîncarcă / Schimbă linkul / Test imprimantă / Ieșire) — util ca să nu se închidă accidental.

---

## 3) Setează modul de printare = Android
Pentru ca serverul să trimită octeții către aplicație:
**Backoffice → Dispozitive → editează dispenserul → Mod printare = `Android`** → Salvează.

(La modul „browser" pagina ar încerca dialogul de print al sistemului; la „Android" trimite ESC/POS direct prin aplicație.)

---

## 4) Imprimanta Bixolon BK3‑31ZC/BEG (USB)
- Conectează imprimanta pe USB la mini‑PC și pornește‑o (hârtie pusă).
- La prima tipărire Android cere permisiunea de acces la dispozitivul USB — apasă **OK** (bifează „Folosește implicit pentru acest dispozitiv" ca să nu mai întrebe).
- Verifică din **meniul Înapoi → Test imprimantă** — trebuie să iasă un bon de test.

Aplicația găsește imprimanta în această ordine: interfață USB de tip *Printer* (clasă 7) → vendor Bixolon (`0x1504`) → primul dispozitiv cu endpoint bulk OUT. Nu necesită SDK de la producător (ESC/POS standard, trimitere RAW pe USB).

---

## 5) Mod kiosk (opțional, recomandat în producție)
Ca să nu poată ieși nimeni din aplicație:
- **Simplu:** Setări Android → Securitate → **Fixare ecran (Screen pinning)**, apoi fixează aplicația.
- **Robust:** setează aplicația ca **device owner** (`adb shell dpm set-device-owner ro.bondeordine.launcher/...`) — aplicația intră singură în Lock Task la pornire (`startLockTask()` e deja apelat).

---

## Depanare
- **„Imprimanta indisponibilă"** → cablu/USB OTG, imprimanta pornită, permisiunea USB acordată. Testează din meniul Înapoi → Test imprimantă.
- **Nu se tipărește la emiterea bonului** → dispozitivul nu e pe **Mod printare = Android**, sau linkul deschis nu e dispenserul corect.
- **Pagina nu se încarcă** → verifică linkul și conexiunea; dacă serverul e pe `http://` simplu, e permis (cleartext activat).
- **Vrei alt link** → buton Înapoi → Schimbă linkul.
