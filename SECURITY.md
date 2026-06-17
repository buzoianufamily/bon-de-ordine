# Securitate — Bon de ordine

Rezumatul măsurilor de securitate și recomandări de hardening.

## Implementat în aplicație
- **Parole**: bcrypt (`password_hash`), re-hash automat la schimbarea algoritmului. Nu se stochează parole în clar.
- **Sesiuni**: cookie `HttpOnly`, `SameSite=Lax`, `Secure` pe HTTPS, legat de host (izolare între subdomenii/tenanti), `session_regenerate_id` la login.
- **CSRF**: token verificat (`hash_equals`) pe toate acțiunile POST din admin și pe API-ul intern al operatorului.
- **SQL**: exclusiv prepared statements (PDO, `EMULATE_PREPARES=false`). Fără concatenare de input în SQL.
- **XSS**: tot ce se afișează trece prin `e()` (`htmlspecialchars`). Datele incluse în blocuri `<script>`/atribute `on*` sunt encodate cu `jsenc()` (`JSON_HEX_*`), deci textele editabile din admin nu pot ieși din context. În JS se folosește escaping la randare.
- **Brute-force**: login — max 10 încercări eșuate / IP în 10 minute (din `audit_log`), apoi cooldown. Schimbarea rapidă de operator prin **PIN** este limitată la 10 încercări / 5 minute.
- **2FA (TOTP)**: autentificare în doi pași opțională pentru conturile de backoffice (Google Authenticator/Authy etc.), implementată în PHP pur (RFC 6238), cu **coduri de recuperare** și politică „obligatoriu pentru admini". Codul QR de configurare este generat **local** (SVG, fără servicii externe) — secretul 2FA nu părăsește serverul. Se activează din Admin → Securitate; un alt admin poate reseta 2FA din Utilizatori.
- **Coduri QR fără servicii externe**: 2FA, cheile dispozitivelor și biletele digitale folosesc un generator QR propriu (`app/core/qr.php`) — funcționează offline și nu expun date unor terți.
- **Audit log**: cine/ce/când a modificat în admin (creare/modificare/ștergere/reset/regenerare cheie). Vezi Admin → Jurnal audit.
- **Antete de securitate**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `HSTS` pe HTTPS — setate în `.htaccess` (mod_headers) și, ca fallback, la nivel PHP în `app/core/init.php`.
- **Foldere/fișiere sensibile blocate** prin `.htaccess`: `app/`, `config/`, `database/` și fișierele `*.sql|*.md|*.log|*.ini|*.json|*.env` (inclusiv `tenants.json`). `Options -Indexes`.
- **Upload media**: validare MIME (finfo), extensii permise, limită 25MB, nume de fișier igienizat.
- **API public** (`/api/v1`): autentificat cu cheie (`X-Api-Key`), comparație `hash_equals`. Cheia se poate regenera.
- **Webhooks**: semnătură `HMAC-SHA256` opțională (`X-Signature`), timeout scurt; URL configurat de admin (fără SSRF din input neîncrezut). Jurnal de livrări (ultimele 100) pentru depanare, în Admin → API & Webhooks.
- **Email (SMTP)**: verificare hostname certificat (`verify_peer` + `verify_peer_name`) la conexiunea TLS — protecție MITM.
- **Printare în rețea**: IP-ul imprimantei (mod „rețea") trebuie să fie un IP valid — împiedică folosirea conexiunii de printare pentru SSRF.
- **Erori**: `display_errors=0` în producție (`app.env=production`).

## Recomandări la instalare
1. **HTTPS obligatoriu** (Let's Encrypt din cPanel). Activează redirect HTTP→HTTPS.
2. **Schimbă contul implicit** `admin@example.ro` / `123456` imediat, din Admin → Utilizatori.
3. Ține `app.env = production` în `config/config.php` (ascunde erorile).
4. Acordă userului MySQL doar privilegii pe baza proprie.
5. Fă backup periodic la baza de date.
6. Pentru API/webhooks: folosește un secret pentru webhook și păstrează cheia API secretă; regenereaz-o dacă a fost expusă.

## Posibile întăriri viitoare (opțional)
- `Content-Security-Policy` strict (atenție la scripturile inline). Widget-ul iframe de pe afișaj rulează deja `sandbox` fără `allow-same-origin` și acceptă doar URL-uri `http(s)`.
- Rotația periodică a cheii API.
- Pe Nginx, replică regulile `.htaccess` (blocarea `config/`, `database/`, `app/`, `*.sql|*.json|*.env`) în configurația serverului.
